<?php

namespace Ovebotai\Exceptions;

// An OAuth step failed: the token endpoint rejected the code/verifier, a
// refresh token was revoked, or a required credential was missing. Distinct
// from ApiException because the caller's remedy is different — reconnect the
// account rather than retry the call.
class AuthException extends OvebotaiException {
}
