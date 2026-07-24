<?php

namespace Ovebotai\Exceptions;

// The API responded, but with a 4xx/5xx status. getCode() carries the HTTP
// status so callers can special-case it (e.g. treat 404 on a KB entry as
// "already gone, nothing to do" rather than a hard failure).
class ApiException extends OvebotaiException {
}
