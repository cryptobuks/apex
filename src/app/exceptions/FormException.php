<?php
declare(strict_types = 1);

namespace apex\app\exceptions;

use apex\app;
use apex\app\exceptions\ApexException;


/**
 * Handles various form errors, such as simple validation errors that call for 
 * a hard error, file upload errors, etc.*/ 
 */
class FormException   extends ApexException
{



    // Properties
    private $error_codes = array(
    'field_required' => "The form field {field} was left blank, and is required"
    );

/**
 * Construct 
 */
public function __construct($message, $field = '')
{ 

    // Set variables
    $vars = array(
        'field' => $field
    );

    // Get message
    $this->log_level = 'error';
    $this->message = $this->error_codes[$message] ?? $message;
    $this->message = fnames($this->message, $vars);

}


}
