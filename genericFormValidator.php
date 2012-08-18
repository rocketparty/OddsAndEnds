<?php
/*
 * Generic Form Validation Class
 * 
 * Usage
 * ---------------------------------------------------------------------
 * You may pass in a custom error message on field set up or rely on the
 * default messages for error notification.
 * 
 * $validator = new FormValidator();
 * $validator->addValidation("Name","req","");
 * $validator->addValidation("Email","req","Please fill in Email");
 * $validator->ValidateForm()
 * 
 * Returns 0 if the form has any invalid inputs, 1 otherwise
 * 
 * Error Information
 * ---------------------------------------------------------------------
 * $error_hash = $validator->GetErrors();
 * foreach($error_hash as $field_name => $error_string) {
 *      echo "<p>$field_name : $error_string</p>\n";
 * }
 * 
 * Provided Validation
 * ---------------------------------------------------------------------
 * req          : Required input
 * maxlen       : Maximum on the field length
 * minlen       : Minimum length on the field length
 * alphamun     : Input is standard alphanumeric
 * alpha        : Input is standard alphabet input
 * num          : Input is standard numeric input
 * email        : Input has the general form of an email address
 * lessthan     : Numeric input is less than a defined minimum (fails if
 *              input is not numeric)
 * regex        : A generic regular expression check with default and custom
 *              error messages available.
 * greaterthan  : Numeric input is greater than a defined maximum (fails 
 *              if input is not numeric)
 * 
 * Additionally there are 4 data cleaning functions available
 * ---------------------------------------------------------------------
 * lower        : All lower case letters returned
 * whitespace   : Removes leading/trailing whitespace
 * caps         : Makes string all caps
 * ucfirst      : Uppercase first letter
 * The valuse are crammed back into _POST
 * 
 */

class FormRule {
    var $rule_name;
    var $rule_def;
    var $field_name;
    var $error_msg;
}

define("FV_REQUIRED_VALUE","Please enter the value for %s");
define("FV_MAXLEN_FAILED","Maximum length exceeded for %s.");
define("FV_MINLEN_FAILED","Please enter input with length more than %d for %s");
define("FV_ALPHANUM_FAILED","Please provide an alpha-numeric (letters and numbers only) input for %s");
define("FV_NUM_FAILED","Please provide numeric input for %s");
define("FV_ALPHA_FAILED","Please provide alphabetic input for %s");
define("FV_EMAIL_FAILED","Please provide a valid email address");
define("FV_LESSTHAN_FAILED","Enter a numeric value less than %f for %s");
define("FV_GREATERTHAN_FAILED","Enter a numeric value greater than %f for %s");
define("FV_REGEXP_FAILED","Please provide a valid input for %s");

class FormValidator {
    // Array of rules
    var $rules;
    // Hash of errors
    var $errors;

    function FormValidator() {
        $this->rules = array();
        $this->errors = array();
    }
    
    function add_rules($field, $rule_name, $rule_def, $custom_error) {
        $frule = new FormRule();
        $frule->field_name = $field;
        $frule->rule_name = $rule_name;
        
        if (isset($rule_def)){
            $frule->rule_def = $rule_def;
        }
        if (isset($custom_error)){
            $frule->error_msg = $custom_error;
        }
        
        array_push($this->rules,$frule);
    }
    
    function get_errors(){
        return $this->errors;
    }
    
    function reset_errors(){
        $this->errors = array();
    }
    
    function reset_rules(){
        $this->rules = array();
    }
    
    /*
     * Takes no input, but looks for anything hiding in $_POST
     * Cycles through the rules attempting to apply them one at a time
     * to the values submitted in the form
     */
    function validate_form() {
        $is_form_valid = 1;
        $error_str = "";
        
        foreach ($this->rules as $field_rule){
            if (! $this->validation_control($field_rule, $_POST, $error_str)){
                $is_form_valid = 0;

                if (isset($this->errors[$field_rule->field_name])){
                    $this->errors[$field_rule->field_name] .= " - " . $error_str;
                }
                else {
                    $this->errors[$field_rule->field_name] = $error_str;
                }
            }
        }
        
        return $is_form_valid;
    }
    
    /*
     * Takes as input:
     *      field_rule - Rule object which defines, applicable field, rule name, custom 
     *          error message and rule parameters.
     *      form_inputs - All submitted inputs
     *      error_message - custom or default error message.
     * This function takes individual passed in rules and checks all available
     * inputs for something to apply the rule to. 
     */
    function validation_control ($field_rule, $form_inputs, &$error_msg){
        $is_valid = 0;
        
        $rule = $field_rule->rule_name;
        $rule_def = "";
        $error_str = "";
        $input_val = "";
        
        if (isset($field_rule->rule_def) && strlen($field_rule->rule_def) > 0){
            $rule_def = $field_rule->rule_def;
        }
        
        if (isset($form_inputs[$field_rule->field_name])){
            $is_valid = $this->validate_field($rule, $rule_def, $form_inputs[$field_rule->field_name], $field_rule->field_name, $error_str);
        }
        
        if (! $is_valid){
            if (isset($field_rule->error_msg) && strlen($field_rule->error_msg) > 0){
                $error_msg = $field_rule->error_msg;
            }
            else {
                $error_msg = $error_str;
            }
        }
        
        return $is_valid;
    }
    
    /* Takes as input:
     *      rule - Name of the rule to apply
     *      rule_def - a parameter that defines how the rule shold operate
     *      field_value - value of the input of a field from a form
     *      field_name - name of an input field from a form
     *      error_msg - a passed back message from a pool of default failure messages
     * This function is called by a controlling function that looks through passed
     * in form values and calls this on each one as is appropriate for the definitions.
     */
    function validate_field ($rule, $rule_def, $field_val, $field_name, &$error_msg){
        
        // Assumed guilty intil proven otherwise
        $is_valid = 0;
        
        // Rules are named here and call each individual validation 
        // function as requested
        switch ($rule)
        {
            case 'req': 
                        {
                            $is_valid = $this->valid_required($field_val, $field_name, &$error_msg);
                            break;
                        }
            case 'maxlen':
                        {
                            $is_valid = $this->valid_maxlen($field_val, $field_name, $rule_def, &$error_msg);
                            break;
                        }
            case 'minlen':
                        {
                            $is_valid = $this->valid_minlen($field_val, $field_name, $rule_def, &$error_msg);
                            break;
                        }
            case 'alphanum':
                        {
                            // Alphanumeric regex check on the input field
                            $is_valid = $this->valid_datatype($field_val, $field_name, 'alphanum');
                            break;
                        }
            case 'num':
                        {
                            // Numeric only input
                            $is_valid = $this->valid_datatype($field_val, $field_name, 'num');
                            break;
                        }
            case 'alpha':
                        {
                            // Alpha only input
                            $is_valid = $this->valid_datatype($field_val, $field_name, 'alpha');
                            break;
                        }
            case 'email':
                        {
                            $is_valid = $this->valid_email($field_val, &$error_msg);
                            break;
                        }
            case 'lessthan':
                        {
                            $is_valid = $this->valid_lessthan($field_val, $field_name, $rule_def, &$error_msg);
                            break;
                        }
            case 'greaterthan':
                        {
                            $is_valid = $this->valid_greaterthan($field_val, $field_name, $rule_def, &$error_msg);
                            break;
                        }
            case 'regex':
                        {
                            $is_valid = $this->valid_regex($field_val, $rule_def);
                            break;
                        }
            case 'lower':
                        {
                            // All lower case
                            $_POST[$field_name] = strtolower($field_val);
                            $is_valid = 1;
                            break;
                        }
            case 'whitespace':
                        {
                            // removes leading/trailing whitespace
                            $_POST[$field_name] = trim($field_val);
                            $is_valid = 1;
                            break;
                        }
            case 'caps':
                        {
                            // makes string all caps
                            $_POST[$field_name] = strtoupper($field_val);
                            $is_valid = 1;
                            break;
                        }
            case 'ucfirst':
                        {
                            // upper case on first letter
                            $_POST[$field_name] = ucfirst($field_val);
                            $is_valid = 1;
                            break;
                        }
        } // end switch
        return $is_valid;
    }
    
    function valid_datatype($field_val, $field_name, $type){
        $is_valid = 0;
        
        if ($type == 'num'){
            $regex = "[^0-9]";
            $def_err = FV_NUM_FAILED;
        }
        else if ($type == 'alpha'){
            $regex = "[^A-Za-z]";
            $def_err = FV_ALPHA_FAILED;
        }
        else {
            $regex = "[^A-Za-z0-9]";
            $def_err = FV_ALPHANUM_FAILED;
        }
        
        if (!ereg($regex, $field_val)){
            $is_valid = 1;
        }
        
        if (!$is_valid){
            $error_msg = sprintf($def_err, $field_name);
        }
        return $is_valid;
    }
    
    function valid_lessthan($field_val, $field_name, $rule_def, &$error_msg){
        $is_valid = 0;
        
        // Want to ensure this is numeric input
        if ($this->valid_datatype($field_val, $field_name, 'num')){
            // Converting to float for accurate comparison
            $less = doubleval($rule_def);
            $in_val = doubleval($field_val);
            if ($in_val < $less){
                $is_valid = 1;
            }
        }
        
        if(!$is_valid){
            $error_msg = sprintf(FV_LESSTHAN_FAILED, $less, $field_name);
        }
        
        return $is_valid;
    }
    
    function valid_greaterthan($field_val, $field_name, $rule_def, &$error_msg){
        $is_valid = 0;
        
        // Numeric input only
        if ($this->valid_datatype($field_val, $field_name, 'num')){
            $greater = doubleval($rule_def);
            $in_val = doubleval($field_val);
            
            if ($in_val > $greater){
                $is_valid = 1;
            }
        }
        
        if(!$is_valid){
            $error_msg = sprintf(FV_GREATERTHAN_FAILED, $greater, $field_name);
        }
        
        return $is_valid;
    }
    
    function valid_regex($field_val, $regex){
        $is_valid = 0;
        
        if (preg_match("$regex", $field_val)){
            $is_valid = 1;
        }
        
        if (!$is_valid){
            $error_msg = sprintf(FV_REGEXP_FAILED, $field_name);
        }
        
        return $is_valid;
    }
    
    /*
     * Don't feel really great about this one. Use at your own risk. 
     * I've had problems with every regex email validation I've
     * ever used. I've not probed this for failure points.
     */
    function valid_email($email, &$error_msg){
        $is_valid = 0;
        
        if (isset($email) && strlen($email)>0){
            if (eregi("^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$", $email)){
                $is_valid = 1;
            }
        }
        
        if (!$is_valid){
            $error_msg = FV_EMAIL_FAILED;
        }
        return $is_valid;
    }
    
    function valid_required($field_val, $field_name, &$error_msg){
        $is_valid = 0;
        
        if (isset($field_val) && strlen($field_val) > 0){
            $is_valid = 1;
        }
        
        if (!$is_valid){
            $error_msg = sprintf(FV_REQUIRED_VALUE, $field_name);
        }
        
        return $is_valid;
    }
    
    function valid_maxlen($field_val, $field_name, $max, &$error_msg){
        $is_valid = 0;
        
        if (isset($field_val)){
            if (strlen($field_val) <= $max){
                $is_valid = 1;
            }
        }
        
        if (!$is_valid){
            $error_msg = sprintf(FV_MAXLEN_FAILED, $field_name);
        }
        
        return $is_valid;
    }
    
    function valid_minlen($field_val, $field_name, $min, &$error_msg){
        $is_valid = 0;
        
        if (isset($field_val)){
            if (strlen($field_val) >= $min){
                $is_valid = 1;
            }
        }
        
        if (!$is_valid){
            $error_msg = sprintf(FV_MINLEN_FAILED, $min, $field_name);
        }
        return $is_valid;
    }
}
?>
