<?php
declare(strict_types = 1);

namespace apex\core\lib\abstracts;

abstract class cron
{

/**
* Processes the crontab job.
*/
abstract public function process();


}
