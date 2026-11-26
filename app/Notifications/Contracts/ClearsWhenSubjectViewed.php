<?php

namespace App\Notifications\Contracts;

/**
 * Marker for inbox notices that should mark read when the viewer opens the subject record.
 * Remediation notices (e.g. lead needed) must not implement this.
 */
interface ClearsWhenSubjectViewed {}
