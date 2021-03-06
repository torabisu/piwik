<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package VisitTime
 */

namespace Piwik\Plugins\VisitTime;

function getTimeLabel($label)
{
    return sprintf(Piwik_Translate('VisitTime_NHour'), $label);
}

/**
 * Returns the day of the week for a date string, without creating a new
 * Date instance.
 *
 * @param string $dateStr
 * @return int The day of the week (1-7)
 */
function dayOfWeekFromDate($dateStr)
{
    return date('N', strtotime($dateStr));
}

/**
 * Returns translated long name of a day of the week.
 *
 * @param int $dayOfWeek 1-7, for Sunday-Saturday
 * @return string
 */
function translateDayOfWeek($dayOfWeek)
{
    return Piwik_Translate('General_LongDay_' . $dayOfWeek);
}
