<?php

namespace Ovebotai\Exceptions;

// The HTTP request never completed - DNS/TLS/timeout/curl-level failure. No
// response body was received, so unlike ApiException there is no status code
// to key off; the connection simply couldn't be made.
class ConnectionException extends OvebotaiException {
}
