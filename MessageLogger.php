<?php

/**
 * Provides various logging methods, error handling, and debugging capabilities.
 * This class was written to function in a legacy system using PHP 5.3
 * Some type hinting is available but not the level that would be available
 * in a later version of PHP.
 *
 * This class offers a centralized approach for:
 *
 * - Logging raw SQL statements and execution results (see `logRawSql`).
 * - Inserting debug entries into a designated database table (see `insertDebugTableLog`).
 * - Customizing error handling behavior, including logging to the console (see `errorHandlerCallback`, `setLogToConsole`).
 * - Writing messages to the browser's developer console for debugging (see `consoleLog`).
 *
 * It's designed for use in both web and console-based PHP applications.
 *
 * @author John Soto
 */
class MessageLogger
{
    /**
     * Flag to determine whether errors should be logged to the browser console.
     *
     * This private static property controls the behavior of the error handler.
     * When set to `true`, errors will be logged to the console using the
     * `logErrorToConsole` function. Otherwise, errors will be displayed on the
     * screen using the `customErrorHandler` function.
     *
     * @var bool
     */
    private static $logToConsole = false;

    /**
     * this is a public static method that inserts a debug log entry into the database.
     *
     * @param string $debug_info Informative message describing the debug point.
     * @param string $sql_statements (Optional) SQL statements executed at the time of logging.
     * @param string $variable_name (Optional) Name of the variable being logged (for reference).
     * @param mixed $variable_value (Optional) Value of the variable being logged.
     *
     */
    public static function insertDebugTableLog($db, $debug_info, $sql_statements, $variable_name = null, $variable_value = null)
    {
        $timestamp = date('Y-m-d H:i:s');
        $debug_info = $db->real_escape_string($debug_info);
        $sql_statements = $db->real_escape_string($sql_statements);
        $variable_name = $db->real_escape_string($variable_name);

        // Serialize the variable value if it's an array
        if (is_array($variable_value)) {
            $variable_value = serialize($variable_value);
        } else {
            $variable_value = $db->real_escape_string($variable_value);
        }

        $sql = "INSERT INTO debug_table_name (debug_info, sql_statements, complete_time, variable_name, variable_value) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssss", $debug_info, $sql_statements, $timestamp, $variable_name, $variable_value);
        $stmt->execute();
    }

    /**
     * Handles PHP errors by displaying them on the screen with a user-friendly format.
     *
     * This function is designed to be a custom error handler for PHP. It takes the
     * standard error handler arguments (`$errno`, `$errstr`, `$errfile`, and `$errline`)
     * and utilizes them to format an informative error message. The message includes
     * the error type, error message, file path where the error occurred, and line
     * number. Additionally, the function defines different CSS styles (`error`,
     * `warning`, and `notice`) to visually distinguish between various error severities.
     * These styles are embedded within the HTML output for immediate effect.
     *
     * This function is typically used in conjunction with `set_error_handler` to
     * replace the default PHP error handling behavior. However, it can also be
     * invoked directly to display a specific error message.
     *
     * @param int $errno The PHP error number (e.g., E_WARNING, E_ERROR).
     * @param string $errstr The error message string.
     * @param string $errfile The path to the file where the error occurred.
     * @param int $errline The line number in the file where the error occurred.
     *
     * @return void
     * 
     * @example 
     * Use a require or autoloader to make sure this script is included in the script you wish to use it in. 
     * Then you can use the following line to make use of the custom error messaging
     * MessageLogger::setupCustomErrorHandler();
     * 
     * * // Sending errors to the browser console instead of the front end:
     * MessageLogger::setLogToConsole(true); // Enable console logging this is false by default and only needs to be changed if you want to send errors to console instead of front end
     * MessageLogger::setupCustomErrorHandler();
     * 
     */
    public static function customErrorHandler($errno, $errstr, $errfile, $errline)
    {
        // Define styles directly within the HTML output
        $styles = "
            <style>
                .error {
                    background-color: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                    padding: 10px;
                    margin: 10px;
                }
    
                .warning {
                    background-color: #ffeeba;
                    color: #856404;
                    border: 1px solid #ffeeba;
                    padding: 10px;
                    margin: 10px;
                }
    
                .notice {
                    background-color: #d1ecf1;
                    color: #0c5460;
                    border: 1px solid #bee5eb;
                    padding: 10px;
                    margin: 10px;
                }
            </style>
        ";

        // Set the appropriate class based on the error type
        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $class = 'error';
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $class = 'warning';
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $class = 'notice';
                break;
            default:
                $class = '';
                break;
        }

        // Output the error message with the appropriate class and embedded styles
        echo $styles;
        echo "<div class='$class'>";
        switch ($errno) {
            case E_ERROR:
                echo "<strong>Error:</strong> [$errno] $errstr<br>";
                break;
            case E_WARNING:
                echo "<strong>Warning:</strong> [$errno] $errstr<br>";
                break;
            case E_NOTICE:
                echo "<strong>Notice:</strong> [$errno] $errstr<br>";
                break;
            default:
                echo "Unknown error type: [$errno] $errstr<br>";
                break;
        }
        echo "File: $errfile<br>";
        echo "Line: $errline<br>";
        echo "</div>";
    }

    /**
     * Handles errors based on the `$logToConsole` flag.
     *
     * This function checks the value of the `$logToConsole` flag. If it's true,
     * errors are logged to the console using `logErrorToConsole`. Otherwise,
     * the default behavior of using `customErrorHandler` for screen display
     * is maintained.
     *
     */
    public static function errorHandlerCallback($errno, $errstr, $errfile, $errline)
    {
        if (self::$logToConsole) {
            self::logErrorToConsole($errno, $errstr, $errfile, $errline);
        } else {
            self::customErrorHandler($errno, $errstr, $errfile, $errline);
        }
    }

    /**
     * Sets up the custom error handler for logging errors.
     *
     * This function uses the `set_error_handler` function to register the
     * class's `errorHandlerCallback` method as the error handler. This ensures
     * that all PHP errors are routed to the `errorHandlerCallback` function for
     * further processing.
     *
     * @return void
     */
    public static function setupCustomErrorHandler()
    {
        set_error_handler(__CLASS__ . '::errorHandlerCallback');
    }

    /**
     * Restores the default PHP error handler.
     *
     * This function calls the `restore_error_handler` function to reset the
     * error handler back to its default behavior. This can be useful if you want
     * to temporarily disable your custom error handling mechanism.
     *
     * @return void
     */
    public static function restoreErrorHandler()
    {
        restore_error_handler();
    }

    /**
     * Writes a message to the browser's developer console for debugging purposes.
     *
     * This function takes the provided data and converts it to a JSON string
     * suitable for logging within the browser console. It then optionally wraps
     * the JSON string in `<script>` tags to ensure proper execution as JavaScript.
     *
     * @param mixed $output The data to be logged to the console. This can be
     *        a string, an array, an object, or any other data type.
     * @param bool $with_script_tags (default: true) Whether to wrap the output
     *        in `<script>` tags. This ensures the code is executed as JavaScript
     *        within the browser console.
     *
     * @return void
     */
    public static function consoleLog($output, $with_script_tags = true)
    {
        $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . ');';
        if ($with_script_tags) {
            $js_code = '<script>' . $js_code . '</script>';
        }
        echo $js_code;
        ob_flush(); // Flush output buffer for immediate console logging
    }

    /**
     * Logs raw SQL statements if there are execution results for that statement.
     *
     * This function logs the provided SQL statement and its execution result
     * to the console. It also includes information about the
     * line number where the function is called and optionally the name of the
     * variable containing the SQL statement.
     *
     * @param mixed $result  Used to check if there are execution results returned.
     * @param string $sql    The raw SQL statement to be logged.
     * @param string $codeLine (optional) The line number where the function is called.
     *                          Defaults to an empty string.
     * @param string $variableName (optional) The name of the variable containing
     *                          the SQL statement. Defaults to "" (empty string).
     * @return void
     * 
     * @example 
     * An SQL statment is at line  227:
     * $sql_to_view = "SELECT * from table_name where condition = $condition";
     * $result_variable = mysql_query($sql_to_view);
     * MessageLogger::logRawSql($result_variable, $sql_to_view, "227", "sql_to_view");
     * 
     */
    public static function logRawSql($result, $sql, $codeLine = "", $variableName = "")
    {
        if ($result !== false) {
            $variable = $variableName ? $variableName : " ";
            $message = "SQL in raw \${$variable} at $codeLine: $sql"; // Use string interpolation with variable

            // Utilize a consistent logging method for flexibility:
            self::consoleLog($message);
        }
    }

    /**
     * Sets the flag to control whether errors are logged to the console.
     *
     * This function allows you to explicitly set the `$logToConsole` flag.
     * Setting the flag to `true` will enable console logging for errors. Setting
     * it to `false` will disable console logging and use the default behavior
     * of displaying errors on the screen.
     *
     * **Type Casting Note:**
     *
     * The `$flag` parameter can be of any data type. However, it will be
     * implicitly cast to a boolean value before being assigned to the flag.
     * Here's how PHP handles type casting in this case:
     *  - Non-zero numbers and non-empty strings evaluate to `true`.
     *  - Zero, empty strings, `null`, and empty arrays evaluate to `false`.
     *
     * @param mixed $flag The value to be cast to a boolean and used as the flag.
     *        True to enable console logging, false to disable.
     *
     * @return void
     */
    public static function setLogToConsole($flag)
    {
        self::$logToConsole = (bool) $flag; // Ensure flag is a boolean
    }

    /**
     * Logs an error message to the browser console.
     *
     * This function takes the details of a PHP error (`$errno`, `$errstr`,
     * `$errfile`, and `$errline`) and prepares them as an associative array
     * (`$errorData`). It then calls the `consoleLog` function to send the
     * error data to the browser's developer console.
     *
     * @param int $errno The PHP error number (e.g., E_WARNING, E_ERROR).
     * @param string $errstr The error message string.
     * @param string $errfile The path to the file where the error occurred.
     * @param int $errline The line number in the file where the error occurred.
     *
     * @return void
     */
    public static function logErrorToConsole($errno, $errstr, $errfile, $errline)
    {
        $errorData = array(
            'errno' => $errno,
            'errstr' => $errstr,
            'errfile' => $errfile,
            'errline' => $errline,
        );
        self::consoleLog($errorData);
    }
}
