<?php
/*
FW Exceptions

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

//just to be able to distinguish between system exceptions and applicaiton-level exceptions
class ApplicationException extends Exception {
} #code in constructor used as return HTTP code, so you can throw exception with 404 for example

#more specific exception - this one should be passed to user
class UserException extends ApplicationException {
}

#Validation is even more specific User exception, used for form input validations
class ValidationException extends UserException {
    public function __construct($msg = '', $code = 400) {
        parent::__construct($msg, $code);
    }
}

class NotFoundException extends UserException {
    public function __construct($msg = 'Not found', $code = 404) {
        parent::__construct($msg, $code);
    }
}

class ExitException extends Exception {
}

class AuthException extends Exception {
    public function __construct($msg = 'Authentication failure', $code = 401) {
        parent::__construct($msg, $code);
    }
}

class NoClassException extends Exception {
}

class NoClassMethodException extends Exception {
}

class NoControllerException extends Exception {
}

class NoModelException extends Exception {
}
