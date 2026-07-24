<?php

namespace Ovebotai\Exceptions;

// Base class for every exception thrown by the Ovebot.ai library. Catching
// this one type is enough to trap any library-originated failure; the
// subclasses below only matter when a caller wants to react differently to a
// transport failure vs. an API rejection vs. an auth problem.
class OvebotaiException extends \Exception {
}
