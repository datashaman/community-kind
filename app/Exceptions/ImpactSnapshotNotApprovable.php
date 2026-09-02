<?php

namespace App\Exceptions;

use RuntimeException;

/*
 * An Organisation that has not activated an impact reporting configuration, or
 * whose configuration approves no metric available for an audience, cannot
 * approve a snapshot. That is an ordinary state for a new tenant, not a
 * programming error, so it is reportable to the person who asked rather than a
 * LogicException that reaches them as a 500.
 */
final class ImpactSnapshotNotApprovable extends RuntimeException {}
