<?php

namespace Codeart\Core\Privacy;

use Illuminate\Session\DatabaseSessionHandler;

/** Laravel's database session handler, with the stored ip_address following `security.ip_storage`. */
final class PrivacyDatabaseSessionHandler extends DatabaseSessionHandler
{
    public function write($sessionId, $data): bool
    {
        $result = parent::write($sessionId, $data);
        $privacy = app(IpPrivacy::class);
        if ($privacy->mode() !== IpPrivacy::FULL) {
            $this->getQuery()->where('id', $sessionId)->whereNotNull('ip_address')->update([
                'ip_address' => $privacy->store(request()->ip()),
            ]);
        }

        return $result;
    }
}
