<?php
/**
 * Debug Helper for WBCOM Demo Installer
 * 
 * This file helps diagnose JSON parsing issues by testing the demo data endpoint
 * and displaying detailed information about the response.
 * 
 * Usage: Access this file directly in your browser after placing it in the plugin directory
 */

// Load WordPress
$wp_load_path = dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
    die( 'Could not find wp-load.php' );
}
require_once( $wp_load_path );

// Check if user is logged in and has admin capabilities
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to access this page.' );
}

// Set up test parameters
$test_theme_slug = isset( $_GET['theme'] ) ? sanitize_text_field( $_GET['theme'] ) : 'reign-theme';
$test_demo_slug = isset( $_GET['demo'] ) ? sanitize_text_field( $_GET['demo'] ) : 'reign-demo1';
$test_target_url = isset( $_GET['url'] ) ? esc_url_raw( $_GET['url'] ) : 'https://demos.wbcomdesigns.com/exporter/reign-theme/';

?>
<!DOCTYPE html>
<html>
<head>
    <title>WBCOM Demo Installer - Debug Helper</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #007cba;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error-box {
            background: #ffebee;
            border: 1px solid #c62828;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #c62828;
        }
        .success-box {
            background: #e8f5e9;
            border: 1px solid #388e3c;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #388e3c;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #f0ad4e;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #856404;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #ddd;
        }
        code {
            background: #f4f4f4;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .test-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .test-form input[type="text"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .test-form button {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .test-form button:hover {
            background: #005a87;
        }
        .json-preview {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WBCOM Demo Installer - Debug Helper</h1>
        
        <div class="info-box">
            <strong>Purpose:</strong> This tool helps diagnose JSON parsing issues in the WBCOM Demo Installer by testing the demo data endpoint and analyzing the response.
        </div>

        <div class="test-form">
            <h2>Test Parameters</h2>
            <form method="get">
                <label>
                    <strong>Theme Slug:</strong>
                    <input type="text" name="theme" value="<?php echo esc_attr( $test_theme_slug ); ?>" />
                </label>
                <label>
                    <strong>Demo Slug:</strong>
                    <input type="text" name="demo" value="<?php echo esc_attr( $test_demo_slug ); ?>" />
                </label>
                <label>
                    <strong>Target URL:</strong>
                    <input type="text" name="url" value="<?php echo esc_attr( $test_target_url ); ?>" />
                </label>
                <button type="submit">Run Test</button>
            </form>
        </div>

        <?php
        if ( isset( $_GET['theme'] ) || isset( $_GET['demo'] ) || isset( $_GET['url'] ) ) {
            echo '<h2>Test Results</h2>';
            
            // Build the request URL
            $api_key = apply_filters( 'wbcom_demo_exporter_api_key', 'demo-export-2024' );
            $request_url = $test_target_url . '?wbcom_theme_demo_listing=yes&api_key=' . $api_key;
            
            echo '<div class="info-box">';
            echo '<strong>Request URL:</strong> <code>' . esc_html( $request_url ) . '</code>';
            echo '</div>';
            
            // Make the request
            $response = wp_remote_post(
                $request_url,
                array(
                    'method'  => 'POST',
                    'timeout' => 120,
                    'headers' => array(
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Accept' => 'application/json',
                    ),
                    'body'    => array(
                        'theme_slug' => $test_theme_slug,
                        'demo_slug'  => $test_demo_slug,
                    ),
                    'sslverify' => false,
                )
            );
            
            // Check for WordPress errors
            if ( is_wp_error( $response ) ) {
                echo '<div class="error-box">';
                echo '<strong>WordPress Error:</strong> ' . esc_html( $response->get_error_message() );
                echo '</div>';
            } else {
                $response_code = wp_remote_retrieve_response_code( $response );
                $response_body = wp_remote_retrieve_body( $response );
                $response_headers = wp_remote_retrieve_headers( $response );
                
                // Display response code
                if ( $response_code === 200 ) {
                    echo '<div class="success-box">';
                    echo '<strong>HTTP Response Code:</strong> ' . esc_html( $response_code ) . ' OK';
                    echo '</div>';
                } else {
                    echo '<div class="error-box">';
                    echo '<strong>HTTP Response Code:</strong> ' . esc_html( $response_code );
                    echo '</div>';
                }
                
                // Display response headers
                echo '<h3>Response Headers</h3>';
                echo '<pre>';
                foreach ( $response_headers as $key => $value ) {
                    echo esc_html( $key . ': ' . $value ) . "\n";
                }
                echo '</pre>';
                
                // Analyze response body
                echo '<h3>Response Body Analysis</h3>';
                
                if ( empty( $response_body ) ) {
                    echo '<div class="error-box">Empty response body received!</div>';
                } else {
                    // Check for PHP errors
                    if ( strpos( $response_body, '<?php' ) !== false ) {
                        echo '<div class="error-box">Response contains PHP tags - server may not be processing PHP correctly!</div>';
                    }
                    
                    if ( preg_match( '/(Warning|Notice|Fatal error|Parse error):/i', $response_body, $matches ) ) {
                        echo '<div class="error-box">Response contains PHP errors!</div>';
                        
                        // Extract error messages
                        preg_match_all( '/(Warning|Notice|Fatal error|Parse error):.*$/mi', $response_body, $error_matches );
                        if ( ! empty( $error_matches[0] ) ) {
                            echo '<h4>PHP Errors Found:</h4>';
                            echo '<pre>';
                            foreach ( $error_matches[0] as $error ) {
                                echo esc_html( $error ) . "\n";
                            }
                            echo '</pre>';
                        }
                    }
                    
                    // Find JSON start
                    $json_start = min(
                        strpos( $response_body, '{' ) !== false ? strpos( $response_body, '{' ) : PHP_INT_MAX,
                        strpos( $response_body, '[' ) !== false ? strpos( $response_body, '[' ) : PHP_INT_MAX
                    );
                    
                    if ( $json_start > 0 && $json_start !== PHP_INT_MAX ) {
                        echo '<div class="warning-box">';
                        echo 'Found non-JSON content before position ' . $json_start;
                        echo '<h4>Content before JSON:</h4>';
                        echo '<pre>' . esc_html( substr( $response_body, 0, $json_start ) ) . '</pre>';
                        echo '</div>';
                        
                        // Extract JSON part
                        $json_body = substr( $response_body, $json_start );
                    } else {
                        $json_body = $response_body;
                    }
                    
                    // Remove BOM if present
                    $bom = pack('H*','EFBBBF');
                    $json_body = preg_replace("/^$bom/", '', $json_body);
                    
                    // Try to parse JSON
                    $json_data = json_decode( $json_body, true );
                    $json_error = json_last_error();
                    
                    if ( $json_error === JSON_ERROR_NONE ) {
                        echo '<div class="success-box">JSON parsed successfully!</div>';
                        
                        // Display JSON structure
                        echo '<h4>JSON Structure:</h4>';
                        echo '<div class="info-box">';
                        
                        if ( isset( $json_data['database_tables'] ) ) {
                            echo '<strong>Database Tables:</strong> ' . count( $json_data['database_tables'] ) . ' tables<br>';
                        } else {
                            echo '<strong>Database Tables:</strong> <span style="color: red;">Missing!</span><br>';
                        }
                        
                        if ( isset( $json_data['upload_folders'] ) ) {
                            echo '<strong>Upload Folders:</strong> ' . count( $json_data['upload_folders'] ) . ' folders<br>';
                        } else {
                            echo '<strong>Upload Folders:</strong> <span style="color: red;">Missing!</span><br>';
                        }
                        
                        echo '</div>';
                        
                        // Display formatted JSON
                        echo '<h4>JSON Data (Preview):</h4>';
                        echo '<div class="json-preview">';
                        echo '<pre>' . esc_html( json_encode( $json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
                        echo '</div>';
                    } else {
                        echo '<div class="error-box">';
                        echo '<strong>JSON Parse Error:</strong> ';
                        switch ( $json_error ) {
                            case JSON_ERROR_DEPTH:
                                echo 'Maximum stack depth exceeded';
                                break;
                            case JSON_ERROR_STATE_MISMATCH:
                                echo 'Underflow or the modes mismatch';
                                break;
                            case JSON_ERROR_CTRL_CHAR:
                                echo 'Unexpected control character found';
                                break;
                            case JSON_ERROR_SYNTAX:
                                echo 'Syntax error, malformed JSON';
                                break;
                            case JSON_ERROR_UTF8:
                                echo 'Malformed UTF-8 characters';
                                break;
                            default:
                                echo 'Unknown error';
                                break;
                        }
                        echo '</div>';
                        
                        // Show raw response for debugging
                        echo '<h4>Raw Response (First 1000 characters):</h4>';
                        echo '<pre>' . esc_html( substr( $response_body, 0, 1000 ) ) . '</pre>';
                        
                        if ( strlen( $response_body ) > 1000 ) {
                            echo '<h4>Raw Response (Last 1000 characters):</h4>';
                            echo '<pre>' . esc_html( substr( $response_body, -1000 ) ) . '</pre>';
                        }
                    }
                }
            }
        }
        ?>
        
        <div class="info-box" style="margin-top: 40px;">
            <h3>Troubleshooting Tips:</h3>
            <ol>
                <li><strong>PHP Errors:</strong> If you see PHP warnings or errors, check the server's error_reporting settings and ensure display_errors is off in production.</li>
                <li><strong>Empty Response:</strong> Check if the target URL is accessible and the demo exporter is properly configured.</li>
                <li><strong>Invalid JSON:</strong> Look for extra whitespace, BOM characters, or PHP output before the JSON data.</li>
                <li><strong>Missing Data:</strong> Ensure the demo exporter is returning both 'database_tables' and 'upload_folders' arrays.</li>
            </ol>
        </div>
    </div>
</body>
</html>