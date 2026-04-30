<?php
/*
FW Exceptions

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

//just to be able to distinguish between system exceptions and applicaiton-level exceptions
#code in constructor used as return HTTP code, so you can throw exception with 404 for example
class ApplicationException extends Exception {
    protected string $localizationString;
    protected array $localizationParameters;
    protected array $context;

    public function __construct($message = "", $code = 0, ?Throwable $previous = null, string $localizationString = "", array $localizationParameters = [], array $context = []) {
        $this->localizationString     = $localizationString;
        $this->localizationParameters = $localizationParameters;
        $this->context                = $context;

        parent::__construct($message, (int)$code, $previous);
    }

    public function getLocalizationString(): string {
        return $this->localizationString;
    }

    public function getLocalizationParameters(): array {
        return $this->localizationParameters;
    }

    public function getContext(): array {
        return $this->context;
    }

    public function getLocalizedMessage($language = "en-US"): string {
        return $this->getMessage();
    }
}

#more specific exception - this one should be passed to user
class UserException extends ApplicationException {
}

class BadRequestException extends UserException {
    public function __construct($msg = 'Bad request', string|int|null $msg_local_or_code = '', array $params = [], array $context = []) {
        $code      = is_int($msg_local_or_code) || (is_string($msg_local_or_code) && ctype_digit($msg_local_or_code)) ? (int)$msg_local_or_code : 400;
        $msg_local = is_string($msg_local_or_code) && !ctype_digit($msg_local_or_code) ? $msg_local_or_code : '';
        parent::__construct($msg, $code, null, $msg_local ?: 'errors.bad_request', $params, $context);
    }
}

#Validation is even more specific User exception, used for form input validations
class ValidationException extends UserException {
    public function __construct($msg = '', $code = 400, string $msg_local = '', array $params = [], array $context = []) {
        parent::__construct($msg, $code, null, $msg_local, $params, $context);
    }
}

class NotFoundException extends UserException {
    public function __construct($msg = 'Not found', string|int|null $msg_local_or_code = '', array $params = [], array $context = []) {
        $code      = is_int($msg_local_or_code) || (is_string($msg_local_or_code) && ctype_digit($msg_local_or_code)) ? (int)$msg_local_or_code : 404;
        $msg_local = is_string($msg_local_or_code) && !ctype_digit($msg_local_or_code) ? $msg_local_or_code : '';
        parent::__construct($msg, $code, null, $msg_local ?: 'errors.not_found', $params, $context);
    }
}

class ExitException extends Exception {
}

class AuthException extends ApplicationException {
    public function __construct($msg = 'Authentication failure', $code = 401, array $params = [], array $context = []) {
        parent::__construct($msg, $code, null, 'errors.authentication_failure', $params, $context);
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
