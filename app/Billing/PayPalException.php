<?php

namespace App\Billing;

use RuntimeException;

/** A PayPal call failed; the message is safe to show to the super admin or log. */
class PayPalException extends RuntimeException {}
