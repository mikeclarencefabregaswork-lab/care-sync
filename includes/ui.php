<?php
// ============================================================
// includes/ui.php  — Shared UI constants (badge classes/icons)
// ============================================================

declare(strict_types=1);

$severity_badge = [
    'mild'     => 'bg-yellow-100 text-yellow-700',
    'moderate' => 'bg-orange-100 text-orange-700',
    'severe'   => 'bg-red-100   text-red-700',
];

$severity_classes = [
    'mild'     => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'moderate' => 'bg-orange-50 text-orange-700 border-orange-200',
    'severe'   => 'bg-red-50   text-red-700   border-red-200',
];

$status_badge = [
    'active'   => 'bg-amber-100  text-amber-700',
    'chronic'  => 'bg-red-100    text-red-700',
    'resolved' => 'bg-emerald-100 text-emerald-700',
];

$status_classes = [
    'active'   => 'bg-amber-50  text-amber-700',
    'chronic'  => 'bg-red-50    text-red-700',
    'resolved' => 'bg-emerald-50 text-emerald-700',
];

$task_type_icons = [
    'medication' => '💊',
    'exercise'   => '🏃',
    'diet'       => '🥗',
    'lifestyle'  => '🌿',
    'other'      => '📋',
];
