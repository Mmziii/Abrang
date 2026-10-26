<?php
/**
 * Abrang image import by SKU.
 *
 * Files named by SKU — SKU.jpg = main image, SKU-2.jpg, SKU-3.jpg ... = gallery
 * (jpg/jpeg/png/webp, case-insensitive). Re-running must not duplicate media
 * (match by SKU + filename hash). Reports SKUs with no image and images with
 * no matching SKU.
 *
 * Usage (inside wpcli):
 *   wp eval-file /scripts/import-images.php /import/images
 *   wp eval-file /scripts/import-images.php /import/images.zip
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

global $args, $argv;
$cli_args = array();
if ( isset( $args ) && is_array( $args ) && ! empty( $args ) ) {
    $cli_args = $args;
} elseif ( isset( $argv ) && is_array( $argv ) && count( $argv ) > 1 ) {
    $cli_args = array_slice( $argv, 1 );
    if ( isset( $cli_args[0] ) && $cli_args[0] === '--' ) {
        $cli_args = array_slice( $cli_args, 1 );
    }
} elseif ( getenv( 'IMPORT_FILE' ) ) {
    $cli_args = array( getenv( 'IMPORT_FILE' ) );
} elseif ( isset( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) && count( $_SERVER['argv'] ) > 1 ) {
    $cli_args = array_slice( $_SERVER['argv'], 1 );
    if ( isset( $cli_args[0] ) && $cli_args[0] === '--' ) {
        $cli_args = array_slice( $cli_args, 1 );
    }
}
$input = $cli_args[0] ?? null;
$dry_run = in_array( '--dry-run', $cli_args, true );

if ( ! $input ) {
    WP_CLI::error( "مسیر پوشه یا فایل فشرده مشخص نشده است. نمونه: wp eval-file /scripts/import-images.php /import/images" );
}

// Resolve path (mounted read-only as /import)
$resolved = $input;
$candidates = array( $input, '/import/' . basename( $input ) );
if ( str_starts_with( $input, './import/' ) ) {
    $candidates[] = '/' . ltrim( $input, './' );
    $candidates[] = '/import/' . basename( $input );
} elseif ( str_starts_with( $input, 'import/' ) ) {
    $candidates[] = '/import/' . basename( $input );
    $candidates[] = '/' . $input;
}
foreach ( $candidates as $c ) {
    if ( file_exists( $c ) ) {
        $resolved = $c;
        break;
    }
}
if ( ! file_exists( $resolved ) ) {
    WP_CLI::error( "مسیر «{$input}» یافت نشد. پوشه یا فایل فشرده را در ./import کپی کنید." );
}

$is_zip = is_file( $resolved ) && strtolower( pathinfo( $resolved, PATHINFO_EXTENSION ) ) === 'zip';
$source_dir = $resolved;
$tmp_extract = null;

if ( $is_zip ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        WP_CLI::error( 'افزونهٔ ZipArchive فعال نیست — باز کردن فایل فشرده ممکن نیست.' );
    }
    $tmp_extract = sys_get_temp_dir() . '/abrang-images-' . uniqid();
    mkdir( $tmp_extract, 0755, true );
    $zip = new ZipArchive();
    if ( true !== $zip->open( $resolved ) ) {
        WP_CLI::error( "فایل فشرده باز نشد: {$resolved}" );
    }
    $zip->extractTo( $tmp_extract );
    $zip->close();
    $source_dir = $tmp_extract;
    WP_CLI::log( "فایل فشرده در «{$tmp_extract}» باز شد." );
}

if ( ! is_dir( $source_dir ) ) {
    WP_CLI::error( "مسیر باید پوشه یا فایل فشرده باشد: {$resolved}" );
}

// Collect files (non-recursive, but also handle one level of subfolders from zip)
$all_files = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source_dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $fileinfo ) {
    if ( ! $fileinfo->isFile() ) {
        continue;
    }
    $ext = strtolower( $fileinfo->getExtension() );
    // getExtension returns last part, handle jpeg etc. Use pathinfo for full.
    $ext = strtolower( pathinfo( $fileinfo->getFilename(), PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, array( 'jpg','jpeg','png','webp' ), true ) ) {
        continue;
    }
    $all_files[] = $fileinfo->getPathname();
}

if ( empty( $all_files ) ) {
    WP_CLI::error( "هیچ تصویر معتبری (jpg/jpeg/png/webp) در «{$resolved}» یافت نشد." );
}

// Build SKU map — list all product SKUs (simple products) to match against.
$product_skus = array();
$product_ids_by_sku = array();
$all_products = get_posts( array(
    'post_type'      => 'product',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );
foreach ( $all_products as $pid ) {
    $sku = get_post_meta( $pid, '_sku', true );
    if ( '' !== $sku ) {
        $product_skus[ strtolower( $sku ) ] = $sku; // lower => original
        $product_ids_by_sku[ strtolower( $sku ) ] = $pid;
        $product_ids_by_sku[ $sku ] = $pid; // also exact
    }
}

// Parse each file into SKU + ordering
$by_sku = array(); // sku_lower => array('sku_original'=>..., 'main'=>filepath, 'gallery'=>[filepath,...])
$no_sku_files = array();

foreach ( $all_files as $filepath ) {
    $filename = basename( $filepath );
    $basename = pathinfo( $filename, PATHINFO_FILENAME ); // without extension
    $lower_basename = strtolower( $basename );

    $matched_sku_lower = null;
    $matched_sku_original = null;
    $gallery_index = null;

    // Try exact match first (main image)
    if ( isset( $product_skus[ $lower_basename ] ) ) {
        $matched_sku_lower = $lower_basename;
        $matched_sku_original = $product_skus[ $lower_basename ];
        $gallery_index = 0; // main
    } else {
        // Try gallery pattern: SKU-2, SKU-3, etc. Handles SKUs that themselves contain dashes.
        // We try to strip trailing -<digits> and see if the remainder is a known SKU.
        if ( preg_match( '/^(.*)-(\d+)$/', $basename, $m ) ) {
            $candidate = strtolower( $m[1] );
            $idx = (int) $m[2];
            if ( isset( $product_skus[ $candidate ] ) && $idx >= 2 ) {
                $matched_sku_lower = $candidate;
                $matched_sku_original = $product_skus[ $candidate ];
                $gallery_index = $idx;
            }
        }
        // Also handle underscore variant SKU_2
        if ( ! $matched_sku_lower && preg_match( '/^(.*)_(\d+)$/', $basename, $m ) ) {
            $candidate = strtolower( $m[1] );
            $idx = (int) $m[2];
            if ( isset( $product_skus[ $candidate ] ) && $idx >= 2 ) {
                $matched_sku_lower = $candidate;
                $matched_sku_original = $product_skus[ $candidate ];
                $gallery_index = $idx;
            }
        }
    }

    if ( ! $matched_sku_lower ) {
        $no_sku_files[] = $filename;
        continue;
    }

    if ( ! isset( $by_sku[ $matched_sku_lower ] ) ) {
        $by_sku[ $matched_sku_lower ] = array(
            'sku_original' => $matched_sku_original,
            'main'         => null,
            'gallery'      => array(),
        );
    }
    if ( 0 === $gallery_index ) {
        // Main image — if multiple mains for same SKU, last one wins but we warn
        if ( $by_sku[ $matched_sku_lower ]['main'] ) {
            WP_CLI::warning( "شناسهٔ «{$matched_sku_original}»: چند تصویر اصلی یافت شد — «" . basename( $by_sku[ $matched_sku_lower ]['main'] ) . "» و «{$filename}» — آخری استفاده می‌شود." );
        }
        $by_sku[ $matched_sku_lower ]['main'] = $filepath;
    } else {
        $by_sku[ $matched_sku_lower ]['gallery'][ $gallery_index ] = $filepath;
    }
}

// Sort gallery by index
foreach ( $by_sku as $k => $v ) {
    ksort( $by_sku[ $k ]['gallery'] );
}

// Report images with no matching SKU
if ( ! empty( $no_sku_files ) ) {
    WP_CLI::log( "── تصاویر بدون محصول همتا (نادیده گرفته شد) ──" );
    foreach ( $no_sku_files as $f ) {
        WP_CLI::log( "  • {$f}" );
    }
}

// Report SKUs with no image
$skus_with_no_image = array();
foreach ( $product_ids_by_sku as $lower => $pid ) {
    // Only consider each SKU once (lower and original duplicate)
    if ( $lower !== strtolower( $lower ) ) {
        continue;
    }
    if ( ! isset( $by_sku[ $lower ] ) ) {
        // Check if lower is duplicate of original handling — ensure we only report each product once
        $orig = $product_skus[ $lower ];
        if ( ! isset( $by_sku[ strtolower( $orig ) ] ) ) {
            // Avoid duplicates for case variations — use a set
        }
    }
}
$seen_reported = array();
foreach ( array_unique( array_values( $product_skus ) ) as $sku_orig ) {
    $lower = strtolower( $sku_orig );
    if ( ! isset( $by_sku[ $lower ] ) ) {
        $skus_with_no_image[] = $sku_orig;
    }
}
if ( ! empty( $skus_with_no_image ) ) {
    WP_CLI::log( "── محصولات بدون تصویر ──" );
    foreach ( array_slice( $skus_with_no_image, 0, 20 ) as $s ) {
        WP_CLI::log( "  • {$s}" );
    }
    if ( count( $skus_with_no_image ) > 20 ) {
        WP_CLI::log( "  … و " . ( count( $skus_with_no_image ) - 20 ) . " مورد دیگر" );
    }
}

if ( empty( $by_sku ) ) {
    WP_CLI::error( "هیچ تصویری با شناسهٔ همتا یافت نشد — واردسازی انجام نشد." );
}

// Import for each SKU
$imported_main = 0;
$imported_gallery = 0;
$skipped_duplicate = 0;

foreach ( $by_sku as $lower => $group ) {
    $sku = $group['sku_original'];
    $pid = $product_ids_by_sku[ $lower ] ?? $product_ids_by_sku[ $sku ] ?? null;
    if ( ! $pid ) {
        continue;
    }
    $product = wc_get_product( $pid );
    if ( ! $product ) {
        continue;
    }

    // Helper to handle a single image file — create attachment if not duplicate, return attachment ID.
    $handle_image = static function ( $filepath, $post_parent, $dry ) {
        $hash = md5_file( $filepath );
        $filename = basename( $filepath );
        // Look for existing attachment with same hash for this product
        $existing = get_posts( array(
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array( 'key' => '_abrang_import_hash', 'value' => $hash ),
            ),
        ) );
        foreach ( $existing as $aid ) {
            // Check if attachment is for this product or has matching filename hash
            $existing_hash = get_post_meta( $aid, '_abrang_import_hash', true );
            $existing_file = get_post_meta( $aid, '_abrang_import_filename', true );
            if ( $existing_hash === $hash ) {
                // Also check parent or that file was previously imported for this SKU
                $parent = (int) get_post_field( 'post_parent', $aid );
                if ( $parent === (int) $post_parent ) {
                    return array( 'id' => $aid, 'duplicate' => true );
                }
                // For gallery duplicates across products? Not relevant. Check filename sku match.
                // If hash matches but parent differs, it's a duplicate file content but different product — treat as new for this product (copy)
                // To avoid cross-product collision, we don't reuse.
            }
        }

        if ( $dry ) {
            return array( 'id' => null, 'duplicate' => false, 'hash' => $hash );
        }

        // Create new attachment via sideload
        $tmp = wp_tempnam( $filename );
        copy( $filepath, $tmp );
        $file_array = array( 'name' => $filename, 'tmp_name' => $tmp );
        $aid = media_handle_sideload( $file_array, $post_parent );
        if ( is_wp_error( $aid ) ) {
            WP_CLI::warning( "خطا در بارگذاری «{$filename}»: " . $aid->get_error_message() );
            if ( file_exists( $tmp ) ) {
                unlink( $tmp );
            }
            return null;
        }
        update_post_meta( $aid, '_abrang_import_hash', $hash );
        update_post_meta( $aid, '_abrang_import_filename', $filename );
        update_post_meta( $aid, '_abrang_import_sku', get_post_meta( $post_parent, '_sku', true ) );
        return array( 'id' => $aid, 'duplicate' => false );
    };

    // Main image
    $new_main_id = null;
    if ( $group['main'] ) {
        $res = $handle_image( $group['main'], $pid, $dry_run );
        if ( null === $res ) {
            // error, skip
        } elseif ( $res['duplicate'] ) {
            $skipped_duplicate++;
            $new_main_id = $res['id'];
            // Ensure product's main image is set to this duplicate (if not already)
            if ( ! $dry_run && (int) $product->get_image_id() !== (int) $res['id'] ) {
                $product->set_image_id( $res['id'] );
            }
        } else {
            if ( $dry_run ) {
                WP_CLI::log( "  [dry-run] تصویر اصلی «{$sku}»: " . basename( $group['main'] ) );
                $imported_main++;
            } else {
                $new_main_id = $res['id'];
                $product->set_image_id( $new_main_id );
                $imported_main++;
                WP_CLI::log( "تصویر اصلی «{$sku}»: " . basename( $group['main'] ) . " → پیوست {$new_main_id}" );
            }
        }
    }

    // Gallery images
    $gallery_ids = array();
    $existing_gallery = $product->get_gallery_image_ids();
    // For dry-run we don't modify, but for real we need to build new gallery
    if ( ! $dry_run ) {
        // Keep existing gallery that is not being replaced? For idempotency we should
        // rebuild from scratch based on files + existing hash matches.
        // We will collect new IDs for each gallery file; duplicates reuse existing IDs.
        foreach ( $group['gallery'] as $idx => $filepath ) {
            $res = $handle_image( $filepath, $pid, false );
            if ( null === $res ) {
                continue;
            }
            if ( $res['duplicate'] ) {
                $skipped_duplicate++;
                $gallery_ids[] = $res['id'];
            } else {
                $gallery_ids[] = $res['id'];
                $imported_gallery++;
                WP_CLI::log( "گالری «{$sku}» #{$idx}: " . basename( $filepath ) . " → پیوست {$res['id']}" );
            }
        }
        // Merge with any existing gallery that wasn't part of this import but has import hash for this SKU?
        // For idempotency, we should not duplicate: if a gallery image file was previously imported
        // and is not in the current folder, we keep it? But spec says re-running must not duplicate,
        // not that missing files should be removed. So we should preserve existing gallery that has
        // import hash and is not in current import set? But then we might keep old gallery images
        // even if owner removed them. Simpler: we preserve existing gallery images that have
        // _abrang_import_hash for this SKU but are not in current import — keep them.
        // However, if the import is re-run with same files, we already handled duplicate detection
        // and will have gallery_ids containing the duplicate IDs. Existing gallery that is duplicate
        // will be included via reuse. If there are extra gallery images from previous import that
        // are not in current folder, we will have removed them if we set gallery to only new.
        // To avoid unintended deletion, we should merge: keep existing gallery IDs that have import hash for this SKU and whose hash not in current files.
        // But easier: just set gallery to the new gallery_ids (replacing). If owner re-runs with same folder, gallery stays same. If they remove a file, gallery will be reduced — maybe intended?
        // For simplicity, we will set gallery to $gallery_ids, which replaces previous gallery for this import.
        // To prevent accidental loss, we can merge: $gallery_ids = array_unique(array_merge($gallery_ids, array_filter($existing_gallery, function($gid) use ($pid) { return get_post_meta($gid, '_abrang_import_sku', true) === get_post_meta($pid, '_sku', true); })));
        // But that would keep old images even if removed. Let's just set to $gallery_ids for now, as spec says re-running must not duplicate — it doesn't say it must preserve removed files.
        $product->set_gallery_image_ids( array_unique( $gallery_ids ) );
    } else {
        foreach ( $group['gallery'] as $idx => $filepath ) {
            WP_CLI::log( "  [dry-run] گالری «{$sku}» #{$idx}: " . basename( $filepath ) );
            $imported_gallery++;
        }
        $skipped_duplicate = 0; // not computed for dry
    }

    if ( ! $dry_run ) {
        $product->save();
    }
}

if ( $tmp_extract && is_dir( $tmp_extract ) ) {
    // Clean up extracted zip
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp_extract, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $it as $fileinfo ) {
        if ( $fileinfo->isDir() ) {
            rmdir( $fileinfo->getPathname() );
        } else {
            unlink( $fileinfo->getPathname() );
        }
    }
    rmdir( $tmp_extract );
}

if ( $dry_run ) {
    WP_CLI::success( "بررسی آزمایشی تمام شد — {$imported_main} تصویر اصلی و {$imported_gallery} گالری آمادهٔ ورود (بدون ذخیره)." );
} else {
    wc_delete_product_transients();
    WP_CLI::success( "واردسازی تصاویر تمام شد — {$imported_main} تصویر اصلی، {$imported_gallery} گالری، {$skipped_duplicate} تکراری نادیده گرفته شد." );
    if ( ! empty( $no_sku_files ) ) {
        WP_CLI::log( "توجه: " . count( $no_sku_files ) . " تصویر بدون محصول همتا نادیده گرفته شد." );
    }
    if ( ! empty( $skus_with_no_image ) ) {
        WP_CLI::log( "توجه: " . count( $skus_with_no_image ) . " محصول بدون تصویر باقی ماند." );
    }
}
