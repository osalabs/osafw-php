<?php

class TestController extends FwController {
    const string route_default_action = FW::ACTION_SHOW;

    const int    access_level = Users::ACL_SITE_ADMIN;

    public function __construct() {
        parent::__construct();
    }

    public function BenchAction(): void {
        rw("hello world");
    }

    public function Bench2Action(): array {
        return array();
    }

    public function MissingClassAction(): void {
        #$model = Notes::i();
        $a = new ABCDE();
        rw("done");
    }

    public function LoggerAction(): void {
        logger("regular debug, should go to Sentry breadcrumbs for regular debugging");
        logger("NOTICE", "NOTICE test for breadcrumbs param1", "param2", array("arr1" => 1, "arr2" => 2));
        logger("ERROR", "Sentry ERROR test");
        logger("WARN", "Sentry WARN test", "sentry param2", array("sentry arr1" => 1, "sentry arr2" => 2));
        logger("DEBUG", "Sentry DEBUG test");
        logger("FATAL", "Sentry FATAL test");
        $ex = new ApplicationException("test Sentry exeption");
        logger("FATAL", $ex, "Sentry FATAL exception");
        rw("done");
    }

}
