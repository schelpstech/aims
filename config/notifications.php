<?php
declare(strict_types=1);
return ['max_attempts'=>max(1,$environment->int('NOTIFICATION_MAX_ATTEMPTS',5))];
