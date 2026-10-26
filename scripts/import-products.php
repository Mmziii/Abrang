<?php
/**
 * Abrang bulk product import — CSV / XLSX by SKU.
 *
 * Usage (inside the wpcli container):
 *   wp eval-file /scripts/import-products.php /import/products.csv
 *   wp eval-file /scripts/import-products.php /import/products.xlsx -- --dry-run
 *   wp eval-file /scripts/import-products.php /import/products.csv -- --skip-invalid
 *
 * Or via the wrapper:
 *   bash /scripts/import-products.sh /import/products.csv [--dry-run] [--skip-invalid]
 *
 * Choice for XLSX: a minimal reader using PHP's ZipArchive + SimpleXML is
 * implemented directly in this file. No network is needed at runtime and no
 * external vendor (PhpSpreadsheet) is bundled — the template XLSX is simple
 * enough (single sheet, shared strings) for a ~120-line reader. The decision
 * is documented in docs/products-template.xlsx generation (openpyxl) and here.
 *
 * All validation is performed first; if any row is invalid the script prints a
 * Persian row-numbered error report and imports nothing, unless --skip-invalid
 * is given (then only valid rows are imported). Prices accept Persian/Arabic
 * digits and commas and are normalized before validation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Argument handling — WP-CLI eval-file places positional args in global $args.
// ---------------------------------------------------------------------------
global $args, $argv;
$cli_args = array();
// Prefer IMPORT_FILE env (set by wrapper) — most reliable for wp eval-file
if ( getenv( 'IMPORT_FILE' ) ) {
    $cli_args = array( getenv( 'IMPORT_FILE' ) );
    $extra = getenv( 'IMPORT_FLAGS' );
    if ( $extra ) {
        $cli_args = array_merge( $cli_args, preg_split( '/\s+/', trim( $extra ) ) );
    }
} elseif ( isset( $args ) && is_array( $args ) && ! empty( $args ) ) {
    $cli_args = $args;
} elseif ( isset( $argv ) && is_array( $argv ) && count( $argv ) > 1 ) {
    // $argv[0] is the script file, rest are args
    // For wp eval-file, $argv may be ['wp','eval-file','/path/to/php','--','/import/file.csv']
    $tmp = array_slice( $argv, 1 );
    if ( isset( $tmp[0] ) && $tmp[0] === 'eval-file' ) {
        $tmp = array_slice( $tmp, 1 );
    }
    if ( isset( $tmp[0] ) && str_ends_with( $tmp[0], '.php' ) ) {
        $tmp = array_slice( $tmp, 1 );
    }
    if ( isset( $tmp[0] ) && $tmp[0] === '--' ) {
        $tmp = array_slice( $tmp, 1 );
    }
    $cli_args = $tmp;
} elseif ( isset( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) && count( $_SERVER['argv'] ) > 1 ) {
    $tmp = array_slice( $_SERVER['argv'], 1 );
    if ( isset( $tmp[0] ) && $tmp[0] === 'eval-file' ) {
        $tmp = array_slice( $tmp, 1 );
    }
    if ( isset( $tmp[0] ) && str_ends_with( $tmp[0], '.php' ) ) {
        $tmp = array_slice( $tmp, 1 );
    }
    if ( isset( $tmp[0] ) && $tmp[0] === '--' ) {
        $tmp = array_slice( $tmp, 1 );
    }
    $cli_args = $tmp;
}
$file     = $cli_args[0] ?? null;
$dry_run  = in_array( '--dry-run', $cli_args, true );
$skip_invalid = in_array( '--skip-invalid', $cli_args, true );

if ( ! $file ) {
    $dbg_args = isset($args) ? json_encode($args) : 'no args';
    $dbg_argv = isset($argv) ? json_encode($argv) : 'no argv';
    WP_CLI::error( "فایل ورودی مشخص نشده است. نمونه: wp eval-file /scripts/import-products.php /import/products.csv -- args={$dbg_args} argv={$dbg_argv} file={$file} cli_args=" . json_encode($cli_args) );
}
if ( ! file_exists( $file ) ) {
    // Try alternative paths that the wrapper may have prepared.
    $alt = '/import/' . basename( $file );
    if ( file_exists( $alt ) ) {
        $file = $alt;
    } else {
        WP_CLI::error( "فایل «{$file}» یافت نشد." );
    }
}
$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
if ( ! in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
    WP_CLI::error( "پسوند فایل باید csv یا xlsx باشد: {$file}" );
}

// Ensure attribute taxonomies are registered (needed for term creation).
// Ensure attribute taxonomies are registered (with cache clear) so term creation
// does not fail with "invalid taxonomy" when attributes were just created.
delete_transient( 'wc_attribute_taxonomies' );
if ( class_exists( 'WC_Cache_Helper' ) ) {
    WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
}
if ( function_exists( 'wc_register_attribute_taxonomies' ) ) {
    wc_register_attribute_taxonomies();
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Normalize Persian/Arabic digits and separators to English digits without
 * separators. Returns the normalized string (may still contain non-numeric
 * characters if the input was invalid).
 */
function abrang_normalize_digits( $value ) {
    if ( null === $value || '' === $value ) {
        return '';
    }
    $value = (string) $value;
    // Persian digits ۰۱۲۳۴۵۶۷۸۹, Arabic-Indic ٠١٢٣٤٥٦٧٨٩, Eastern Arabic ۰۱۲۳۴۵۶۷۸۹ (same as Persian)
    $map = array(
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        // Normalize Arabic ي/ك to Persian ی/ک for search/import consistency (query-only)
        'ي' => 'ی', 'ك' => 'ک',
    );
    $value = strtr( $value, $map );
    // Remove thousand separators: Latin comma, Persian comma ،, Arabic thousand ٬, narrow no-break space, space
    $value = str_replace( array( ',', '،', '٬', '٬', "\xE2\x80\x8C", ' ', ' ', '٫' ), '', $value );
    // Also remove any remaining non-breaking spaces or ZWNJ
    $value = trim( $value );
    return $value;
}

/**
 * Parse a price-like string that may contain Persian digits and commas.
 * Returns int on success, null if empty, or WP_Error-like string on failure.
 */
function abrang_parse_price( $raw, $field, $row_num, &$errors ) {
    if ( null === $raw || '' === trim( (string) $raw ) ) {
        return null;
    }
    $normalized = abrang_normalize_digits( $raw );
    // After normalization only digits should remain (prices are integers, 0 decimals)
    if ( '' === $normalized || ! preg_match( '/^\d+$/', $normalized ) ) {
        $errors[] = "ردیف {$row_num}: ستون {$field} باید عدد باشد — مقدار فعلی «{$raw}» معتبر نیست.";
        return false;
    }
    $value = (int) $normalized;
    if ( $value < 0 ) {
        $errors[] = "ردیف {$row_num}: ستون {$field} نمی‌تواند منفی باشد.";
        return false;
    }
    return $value;
}

function abrang_parse_int( $raw, $field, $row_num, &$errors, $allow_empty = true ) {
    if ( null === $raw || '' === trim( (string) $raw ) ) {
        return $allow_empty ? null : 0;
    }
    $normalized = abrang_normalize_digits( $raw );
    if ( '' === $normalized || ! preg_match( '/^\d+$/', $normalized ) ) {
        $errors[] = "ردیف {$row_num}: ستون {$field} باید عدد صحیح باشد — «{$raw}» معتبر نیست.";
        return false;
    }
    return (int) $normalized;
}

/**
 * Ensure a taxonomy term exists; create if missing. Returns term slug.
 */
function abrang_ensure_term( $taxonomy, $name ) {
    $name = trim( (string) $name );
    if ( '' === $name ) {
        return null;
    }
    // Ensure taxonomy is registered (for pa_* attributes) before term lookup.
    if ( str_starts_with( $taxonomy, 'pa_' ) && ! taxonomy_exists( $taxonomy ) ) {
        delete_transient( 'wc_attribute_taxonomies' );
        if ( class_exists( 'WC_Cache_Helper' ) ) {
            WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
        }
        if ( function_exists( 'wc_register_attribute_taxonomies' ) ) {
            wc_register_attribute_taxonomies();
        }
    }
    // For attribute taxonomies the term name is the label; slug is auto-generated.
    $existing = get_term_by( 'name', $name, $taxonomy );
    if ( $existing && ! is_wp_error( $existing ) ) {
        return $existing->slug;
    }
    $existing = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
    if ( $existing && ! is_wp_error( $existing ) ) {
        return $existing->slug;
    }
    $result = wp_insert_term( $name, $taxonomy, array( 'slug' => sanitize_title( $name ) ) );
    if ( is_wp_error( $result ) ) {
        if ( 'term_exists' === $result->get_error_code() ) {
            $term = get_term( $result->get_error_data(), $taxonomy );
            return $term ? $term->slug : sanitize_title( $name );
        }
        WP_CLI::warning( "ایجاد برچسب/ویژگی «{$name}» در {$taxonomy} شکست خورد: " . $result->get_error_message() );
        return null;
    }
    return get_term( $result['term_id'], $taxonomy )->slug ?? sanitize_title( $name );
}

// ---------------------------------------------------------------------------
// XLSX minimal reader
// ---------------------------------------------------------------------------

function abrang_read_xlsx( $path ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        WP_CLI::error( 'افزونهٔ ZipArchive در PHP فعال نیست — خواندن XLSX ممکن نیست.' );
    }
    $zip = new ZipArchive();
    if ( true !== $zip->open( $path ) ) {
        WP_CLI::error( "فایل XLSX باز نشد: {$path}" );
    }

    // Shared strings
    $shared = array();
    $data = $zip->getFromName( 'xl/sharedStrings.xml' );
    if ( false !== $data ) {
        $xml = simplexml_load_string( $data );
        if ( $xml ) {
            foreach ( $xml->si as $si ) {
                // Concatenate all t nodes inside si (handles rich text) — use local-name() to avoid namespace prefix issues
                $text = '';
                $t_nodes = $si->xpath( './/*[local-name()="t"]' );
                if ( $t_nodes ) {
                    foreach ( $t_nodes as $t ) {
                        $text .= (string) $t;
                    }
                }
                // Fallback: si->t directly
                if ( '' === $text && isset( $si->t ) ) {
                    $text = (string) $si->t;
                }
                $shared[] = $text;
            }
        }
    }

    $sheet_data = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
    if ( false === $sheet_data ) {
        // Try alternative sheet name (openpyxl may use sheet1 only)
        for ( $i = 1; $i <= 5; $i++ ) {
            $sheet_data = $zip->getFromName( "xl/worksheets/sheet{$i}.xml" );
            if ( false !== $sheet_data ) {
                break;
            }
        }
    }
    if ( false === $sheet_data ) {
        $zip->close();
        WP_CLI::error( 'برگهٔ Sheet1 در فایل XLSX یافت نشد.' );
    }

    $xml = simplexml_load_string( $sheet_data );
    if ( ! $xml ) {
        $zip->close();
        WP_CLI::error( 'تجزیهٔ Sheet1 ناموفق بود.' );
    }
    $rows = array();
    // sheetData/row — use local-name() to avoid namespace issues in wp eval-file context
    $row_nodes = $xml->xpath( '//*[local-name()="sheetData"]/*[local-name()="row"]' );
    if ( ! $row_nodes ) {
        $zip->close();
        return $rows;
    }
    foreach ( $row_nodes as $row_node ) {
        $cells = array();
        foreach ( $row_node->c as $c ) {
            $type = (string) ( $c['t'] ?? '' );
            $value = null;
            if ( isset( $c->v ) ) {
                $v = (string) $c->v;
                if ( 's' === $type ) {
                    $idx = (int) $v;
                    $value = $shared[ $idx ] ?? '';
                } else {
                    $value = $v;
                }
            } elseif ( isset( $c->is ) ) {
                // Inline string — use local-name() to avoid namespace prefix
                $t_nodes = $c->is->xpath( './/*[local-name()="t"]' );
                $value = '';
                if ( $t_nodes ) {
                    foreach ( $t_nodes as $t ) {
                        $value .= (string) $t;
                    }
                }
            } else {
                $value = '';
            }
            $cells[] = $value;
        }
        $rows[] = $cells;
    }
    $zip->close();
    return $rows;
}

// ---------------------------------------------------------------------------
// CSV reader (handles BOM)
// ---------------------------------------------------------------------------

function abrang_read_csv( $path ) {
    $content = file_get_contents( $path );
    if ( false === $content ) {
        WP_CLI::error( "فایل CSV خوانده نشد: {$path}" );
    }
    // Strip BOM if present (UTF-8 with BOM for Excel on Windows)
    if ( str_starts_with( $content, "\xEF\xBB\xBF" ) ) {
        $content = substr( $content, 3 );
    }
    $tmp = tempnam( sys_get_temp_dir(), 'abrang_csv' );
    file_put_contents( $tmp, $content );

    $handle = fopen( $tmp, 'r' );
    if ( ! $handle ) {
        WP_CLI::error( "فایل موقت CSV باز نشد." );
    }
    $rows = array();
    while ( ( $data = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
        // Skip completely empty lines
        $all_empty = true;
        foreach ( $data as $v ) {
            if ( '' !== trim( (string) $v ) ) {
                $all_empty = false;
                break;
            }
        }
        if ( $all_empty ) {
            continue;
        }
        $rows[] = $data;
    }
    fclose( $handle );
    unlink( $tmp );
    return $rows;
}

// ---------------------------------------------------------------------------
// Read file into associative rows
// ---------------------------------------------------------------------------

$raw_rows = null;
if ( 'csv' === $ext ) {
    $raw_rows = abrang_read_csv( $file );
} else {
    $raw_rows = abrang_read_xlsx( $file );
}

if ( empty( $raw_rows ) ) {
    WP_CLI::error( 'فایل خالی است یا قابل خواندن نیست.' );
}

// First row is header
$header = array_map( static function ( $h ) {
    return strtolower( trim( (string) $h ) );
}, $raw_rows[0] );

$expected = array( 'sku','name','category_slug','regular_price','sale_price','stock_qty','short_description','description','brand','color','size','material','pack_size','weight_grams','tags','status' );
$missing_headers = array_diff( $expected, $header );
if ( ! empty( $missing_headers ) ) {
    WP_CLI::error( 'ستون‌های ضروری در سربرگ یافت نشد: ' . implode( ', ', $missing_headers ) );
}

// Map header index
$col_index = array_flip( $header );

// Detect and skip the Persian comment row in the XLSX template (row 2 contains
// descriptions like «شناسهٔ یکتا» rather than real SKUs). For CSV there is no
// comment row, so this check does nothing.
$data_start = 1;
if ( 'xlsx' === $ext && isset( $raw_rows[1] ) ) {
    $maybe_comment = $raw_rows[1][ $col_index['sku'] ?? 0 ] ?? '';
    if ( '' !== $maybe_comment && ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_\-]*$/', trim( (string) $maybe_comment ) ) ) {
        // Heuristic: if the SKU-like cell contains Persian or spaces, it's the comment row.
        if ( preg_match( '/[آ-ی]/u', $maybe_comment ) || str_contains( $maybe_comment, 'شناسه' ) || str_contains( $maybe_comment, 'SKU' ) ) {
            $data_start = 2;
        } elseif ( trim( (string) $maybe_comment ) === '' ) {
            // Empty second row? Treat as comment as well if next row looks like data.
        }
    }
    // Also check if row 2's SKU is not matching typical SKU pattern but row 3 does — then row2 is comment.
    if ( $data_start === 1 && isset( $raw_rows[2] ) ) {
        $row2_sku = trim( (string) ( $raw_rows[1][ $col_index['sku'] ?? 0 ] ?? '' ) );
        $row3_sku = trim( (string) ( $raw_rows[2][ $col_index['sku'] ?? 0 ] ?? '' ) );
        if ( preg_match( '/[آ-ی]/u', $row2_sku ) && preg_match( '/^[A-Za-z0-9_-]+$/', $row3_sku ) ) {
            $data_start = 2;
        }
    }
}

$rows = array_slice( $raw_rows, $data_start );
if ( empty( $rows ) ) {
    WP_CLI::warning( 'هیچ ردیف داده‌ای یافت نشد.' );
    WP_CLI::success( 'کاری انجام نشد.' );
    return;
}

// ---------------------------------------------------------------------------
// Validation — collect all errors first, import nothing unless --skip-invalid
// ---------------------------------------------------------------------------

$errors = array();
$seen_skus = array();
$valid_rows = array(); // rows that passed validation (used when --skip-invalid)
$all_rows_data = array(); // parsed data for each row (for import)

foreach ( $rows as $idx => $raw ) {
    // Row number in the original file (header = 1, comment row maybe 2, etc.)
    $file_row_num = $data_start + $idx + 2; // +1 for 0-index to 1-index, +1 for header
    // Pad row to header length
    $raw = array_pad( $raw, count( $header ), '' );
    $assoc = array();
    foreach ( $header as $col_pos => $col_name ) {
        $assoc[ $col_name ] = isset( $raw[ $col_pos ] ) ? trim( (string) $raw[ $col_pos ] ) : '';
    }

    $row_errors = array();
    $sku = trim( (string) ( $assoc['sku'] ?? '' ) );
    $name = trim( (string) ( $assoc['name'] ?? '' ) );

    if ( '' === $sku ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون sku الزامی است و خالی است.";
    } else {
        // Duplicate SKU inside the file
        $sku_key = strtolower( $sku );
        if ( isset( $seen_skus[ $sku_key ] ) ) {
            $row_errors[] = "ردیف {$file_row_num}: شناسهٔ یکتا «{$sku}» در ردیف {$seen_skus[$sku_key]} نیز آمده است — شناسه‌ها باید یکتا باشند.";
        } else {
            $seen_skus[ $sku_key ] = $file_row_num;
        }
        // SKU format check (allow latin letters, digits, -, _)
        if ( ! preg_match( '/^[A-Za-z0-9_\-]+$/', $sku ) ) {
            $row_errors[] = "ردیف {$file_row_num}: شناسهٔ یکتا «{$sku}» معتبر نیست — فقط حروف انگلیسی، عدد، - و _ مجاز است.";
        }
    }

    if ( '' === $name && '' !== $sku ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون name (نام محصول) الزامی است.";
    }

    // Category slugs
    $cat_raw = $assoc['category_slug'] ?? '';
    if ( '' === trim( (string) $cat_raw ) ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون category_slug الزامی است.";
    } else {
        $slugs = array_filter( array_map( 'trim', explode( '|', $cat_raw ) ) );
        if ( empty( $slugs ) ) {
            $row_errors[] = "ردیف {$file_row_num}: ستون category_slug خالی است.";
        } else {
            foreach ( $slugs as $slug ) {
                $term = get_term_by( 'slug', $slug, 'product_cat' );
                if ( ! $term || is_wp_error( $term ) ) {
                    $row_errors[] = "ردیف {$file_row_num}: دسته با slug «{$slug}» یافت نشد.";
                }
            }
        }
    }

    // Prices
    $regular = abrang_parse_price( $assoc['regular_price'] ?? '', 'regular_price', $file_row_num, $row_errors );
    $sale    = abrang_parse_price( $assoc['sale_price'] ?? '', 'sale_price', $file_row_num, $row_errors );

    if ( false !== $regular && null !== $regular && $regular <= 0 ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون regular_price باید بزرگ‌تر از صفر باشد.";
    }
    if ( false !== $sale && null !== $sale && $sale <= 0 ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون sale_price باید بزرگ‌تر از صفر باشد.";
    }
    if ( false !== $regular && false !== $sale && null !== $regular && null !== $sale && $sale >= $regular ) {
        $row_errors[] = "ردیف {$file_row_num}: قیمت حراج ({$sale}) باید کمتر از قیمت اصلی ({$regular}) باشد.";
    }
    if ( '' === trim( (string) ( $assoc['regular_price'] ?? '' ) ) ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون regular_price الزامی است.";
    }

    // Stock
    $stock = abrang_parse_int( $assoc['stock_qty'] ?? '', 'stock_qty', $file_row_num, $row_errors, true );
    if ( false !== $stock && null !== $stock && $stock < 0 ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون stock_qty نمی‌تواند منفی باشد.";
    }

    // Weight
    $weight = abrang_parse_int( $assoc['weight_grams'] ?? '', 'weight_grams', $file_row_num, $row_errors, true );
    if ( false !== $weight && null !== $weight && $weight < 0 ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون weight_grams نمی‌تواند منفی باشد.";
    }

    // Status
    $status = strtolower( trim( (string) ( $assoc['status'] ?? '' ) ) );
    if ( '' !== $status && ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
        $row_errors[] = "ردیف {$file_row_num}: ستون status فقط می‌تواند publish یا draft باشد — مقدار فعلی «{$assoc['status']}» معتبر نیست.";
    }

    if ( ! empty( $row_errors ) ) {
        foreach ( $row_errors as $e ) {
            $errors[] = $e;
        }
    } else {
        $valid_rows[] = $file_row_num;
        $all_rows_data[] = array(
            'row_num'          => $file_row_num,
            'sku'              => $sku,
            'name'             => $name,
            'category_slug'    => $cat_raw,
            'regular_price'    => $regular,
            'sale_price'       => $sale,
            'stock_qty'        => $stock,
            'short_description'=> $assoc['short_description'] ?? '',
            'description'      => $assoc['description'] ?? '',
            'brand'            => $assoc['brand'] ?? '',
            'color'            => $assoc['color'] ?? '',
            'size'             => $assoc['size'] ?? '',
            'material'         => $assoc['material'] ?? '',
            'pack_size'        => $assoc['pack_size'] ?? '',
            'weight_grams'     => $weight,
            'tags'             => $assoc['tags'] ?? '',
            'status'           => $status !== '' ? $status : 'publish',
        );
    }
}

// If there are errors and --skip-invalid not set, abort without importing.
if ( ! empty( $errors ) && ! $skip_invalid ) {
    WP_CLI::log( "── گزارش خطاهای پروندهٔ «{$file}» ──" );
    foreach ( $errors as $e ) {
        WP_CLI::log( "  ✗ {$e}" );
    }
    WP_CLI::log( "تعداد ردیف‌های دارای خطا: " . count( $errors ) );
    WP_CLI::log( "هیچ محصولی وارد نشد — خطاها را برطرف و دوباره تلاش کنید. یا با --skip-invalid فقط ردیف‌های سالم وارد می‌شوند." );
    WP_CLI::error( "واردسازی متوقف شد — " . count( $errors ) . " خطا یافت شد." );
}

if ( ! empty( $errors ) && $skip_invalid ) {
    WP_CLI::log( "── گزارش خطاها (با --skip-invalid فقط ردیف‌های سالم وارد می‌شوند) ──" );
    foreach ( $errors as $e ) {
        WP_CLI::warning( $e );
    }
    WP_CLI::log( "ردیف‌های معتبر: " . count( $valid_rows ) . " از " . count( $rows ) );
    if ( empty( $all_rows_data ) ) {
        WP_CLI::error( "هیچ ردیف معتبری برای ورود وجود ندارد." );
    }
}

// For --skip-invalid we only import valid rows; otherwise we import all (since no errors)
$to_import = $all_rows_data;

if ( $dry_run ) {
    WP_CLI::log( "── حالت آزمایشی (--dry-run) — هیچ تغییری ذخیره نمی‌شود ──" );
    WP_CLI::log( "فایل: {$file} — " . count( $to_import ) . " ردیف برای ورود آماده است:" );
    foreach ( $to_import as $r ) {
        $existing = wc_get_product_id_by_sku( $r['sku'] );
        $mode = $existing ? 'به‌روزرسانی' : 'ایجاد';
        WP_CLI::log( "  • ردیف {$r['row_num']}: {$mode} «{$r['sku']}» — «{$r['name']}» — دسته: {$r['category_slug']} — قیمت: {$r['regular_price']}" . ( $r['sale_price'] ? " حراج: {$r['sale_price']}" : "" ) );
    }
    WP_CLI::success( "بررسی آزمایشی تمام شد — " . count( $to_import ) . " ردیف معتبر." );
    return;
}

// ---------------------------------------------------------------------------
// Import
// ---------------------------------------------------------------------------

$imported = 0;
$updated  = 0;
$created  = 0;

foreach ( $to_import as $row ) {
    $sku = $row['sku'];
    $existing_id = wc_get_product_id_by_sku( $sku );
    $is_update = (bool) $existing_id;

    if ( $existing_id ) {
        $product = wc_get_product( $existing_id );
        if ( ! $product ) {
            WP_CLI::warning( "ردیف {$row['row_num']}: محصول با شناسهٔ «{$sku}» یافت شد اما بارگذاری نشد — رد شد." );
            continue;
        }
        // Do not allow changing a variable product via this import (simple only).
        if ( ! $product->is_type( 'simple' ) ) {
            WP_CLI::warning( "ردیف {$row['row_num']}: محصول «{$sku}» از نوع «{$product->get_type()}» است — فقط محصولات ساده از طریق فایل وارد می‌شوند، رد شد." );
            continue;
        }
    } else {
        $product = new WC_Product_Simple();
        $product->set_sku( $sku );
    }

    $product->set_name( $row['name'] );
    $product->set_status( $row['status'] );
    $product->set_short_description( $row['short_description'] );
    $product->set_description( $row['description'] );
    $product->set_regular_price( $row['regular_price'] );
    if ( null !== $row['sale_price'] && '' !== $row['sale_price'] ) {
        $product->set_sale_price( $row['sale_price'] );
    } else {
        $product->set_sale_price( '' );
    }

    // Stock
    if ( null !== $row['stock_qty'] ) {
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $row['stock_qty'] );
        $product->set_stock_status( $row['stock_qty'] > 0 ? 'instock' : 'outofstock' );
    } else {
        $product->set_manage_stock( false );
        $product->set_stock_status( 'instock' );
    }

    // Weight
    if ( null !== $row['weight_grams'] && '' !== $row['weight_grams'] ) {
        // Store weight uses the unit configured in WooCommerce (we set it to grams in setup.sh).
        $product->set_weight( (string) $row['weight_grams'] );
    } else {
        $product->set_weight( '' );
    }

    // Categories
    $cat_slugs = array_filter( array_map( 'trim', explode( '|', $row['category_slug'] ) ) );
    $cat_ids = array();
    foreach ( $cat_slugs as $slug ) {
        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) ) {
            $cat_ids[] = (int) $term->term_id;
            // Also include parent so breadcrumbs work, mirroring demo fixture behaviour.
            if ( $term->parent ) {
                $cat_ids[] = (int) $term->parent;
            }
        }
    }
    $cat_ids = array_unique( $cat_ids );
    $product->set_category_ids( $cat_ids );

    // Tags — split by | or ، or , or ؛
    $tags_raw = $row['tags'];
    $tag_ids = array();
    if ( '' !== trim( (string) $tags_raw ) ) {
        $parts = preg_split( '/[|،,؛]+/u', $tags_raw );
        foreach ( $parts as $t ) {
            $t = trim( $t );
            if ( '' === $t ) {
                continue;
            }
            $slug = abrang_ensure_term( 'product_tag', $t );
            if ( $slug ) {
                $term = get_term_by( 'slug', $slug, 'product_tag' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $tag_ids[] = (int) $term->term_id;
                } else {
                    // Tag was just created with sanitized slug
                    $term = get_term_by( 'name', $t, 'product_tag' );
                    if ( $term && ! is_wp_error( $term ) ) {
                        $tag_ids[] = (int) $term->term_id;
                    }
                }
            }
        }
    }
    $product->set_tag_ids( $tag_ids );

    // Attributes — brand, color, size, material, pack-size
    $attr_map = array(
        'brand'     => 'pa_brand',
        'color'     => 'pa_color',
        'size'      => 'pa_size',
        'material'  => 'pa_material',
        'pack_size' => 'pa_pack-size',
    );
    $attributes = array();
    $pos = 0;
    foreach ( $attr_map as $col => $taxonomy ) {
        $val = trim( (string) ( $row[ $col ] ?? '' ) );
        if ( '' === $val ) {
            continue;
        }
        // Split multiple values if the owner uses |.
        $values = array_filter( array_map( 'trim', preg_split( '/[|]+/u', $val ) ) );
        $slugs = array();
        foreach ( $values as $v ) {
            $slug = abrang_ensure_term( $taxonomy, $v );
            if ( $slug ) {
                $slugs[] = $slug;
            }
        }
        if ( empty( $slugs ) ) {
            continue;
        }
        $attr = new WC_Product_Attribute();
        $attr->set_id( wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) ) );
        $attr->set_name( $taxonomy );
        $attr->set_options( $slugs );
        $attr->set_position( $pos++ );
        $attr->set_visible( true );
        $attr->set_variation( false );
        $attributes[] = $attr;
    }
    // Preserve existing attributes that are not part of the import file?
    // The import file is the source of truth for these five attributes — if a
    // column is empty we remove that attribute from the product.
    $product->set_attributes( $attributes );

    $id = $product->save();
    if ( is_wp_error( $id ) ) {
        WP_CLI::warning( "ردیف {$row['row_num']}: خطا در ذخیرهٔ «{$sku}»: " . $id->get_error_message() );
        continue;
    }
    if ( $is_update ) {
        $updated++;
        WP_CLI::log( "به‌روزرسانی: «{$sku}» (ردیف {$row['row_num']})" );
    } else {
        $created++;
        WP_CLI::log( "ایجاد: «{$sku}» (ردیف {$row['row_num']})" );
    }
    $imported++;
}

wc_delete_product_transients();
WP_CLI::success( "واردسازی تمام شد — {$imported} محصول ( {$created} ایجاد، {$updated} به‌روزرسانی )." );
