
<?php
class VoteTokenService {
    public static function generate($meetingId, $memberId, $resolutionId, $secret) {
        $token = uuid_create(UUID_TYPE_RANDOM);
        $hash = hash_hmac('sha256', $token, $secret);
        return [$token, $hash];
    }
}
