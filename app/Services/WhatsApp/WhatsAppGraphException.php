<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/** A Meta Graph API failure; the message is safe to show to an admin (no URLs, tokens or secrets). */
class WhatsAppGraphException extends RuntimeException {}
