<?php

declare(strict_types=1);

return ['media_path'=>$environment->get('CMS_MEDIA_PATH','storage/private/cms-media'),'max_upload_bytes'=>$environment->int('CMS_MAX_UPLOAD_BYTES',10485760)];
