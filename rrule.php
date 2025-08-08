#!/usr/local/bin/php
<?php
// Copyright 2022-2099, MHE Vos, SDS82

// Based on:
// 		https://github.com/ZContent/icalendar/blob/master/includes/recurringdate.php
// and because that one only works half, I made this one.

// This version parses all examples from
//		https://icalendar.org/iCalendar-RFC-5545/3-8-5-3-recurrence-rule.html
// correctly, except for SETPOS. I did not implement that one.

date_default_timezone_set('Europe/Amsterdam');
ini_set('display_errors', 'on');

define('_ZAPCAL_MAXYEAR', (date('Y') + 99));

// --------------------------------------------------------------------------------

// Check if end of a rule has been reached
function local_maxDates()
{
	global $rr_repeatmode, $rr_count, $rr_until, $rr_dates;

	// To prevent incorrect count()
	$rr_dates = array_unique($rr_dates);

	if ($rr_repeatmode == "c" && count($rr_dates) >= $rr_count)
	{
		return(true); // exceeded count
	}
	elseif (count($rr_dates) > 0 && $rr_repeatmode == "u" && end($rr_dates) > $rr_until)
	{
		return(true); //past date
	}
	return false;
}

// --------------------------------------------------------------------------------

function local_addDate($date, $hour, $min, $sec, $month, $day, $year)
{
	$sqldate = date('Y-m-d H:i:s', $date);

	$tdate = array();
	$tdate["year"] = substr($sqldate, 0,4);
	$tdate["mon"] = substr($sqldate,5,2);
	$tdate["mday"] = substr($sqldate,8,2);
	$tdate["hours"] = substr($sqldate,11,2);
	$tdate["minutes"] = substr($sqldate,14,2);
	$tdate["seconds"] = substr($sqldate,17,2);

	$newdate = mktime(($tdate["hours"] + $hour), ($tdate["minutes"] + $min), ($tdate["seconds"] + $sec), ($tdate["mon"] + $month), ($tdate["mday"] + $day), ($tdate["year"] + $year));

	return($newdate);
}

// --------------------------------------------------------------------------------

/* Date math: get date from week and day in specifiec month
*
* This routine finds actual dates for the second Tuesday of the month, last Friday of the month, etc.
* For second Tuesday, use $weekinmonth = 1, $wday = 2
* for last Friday, use $weekinmonth = -1, $wday = 5
*/
function local_getDateFromDay($date, $weekinmonth, $wday)
{
	// determine first day in month
	$tdate = getdate($date);
	$monthbegin = mktime(0, 0, 0, $tdate["mon"], 1, $tdate["year"]);
	$monthend = local_addDate($monthbegin, 0, 0, -1, 1, 0, 0); // add 1 month and subtract 1 second
	$day = local_addDate($date, 0, 0, 0, 0, (1 - $tdate["mday"]), 0);

	$month = array(array());
	while ($day <= $monthend)
	{
		$tdate = getdate($day);
		$month[$tdate["wday"]][] = $day;
		$day = local_addDate($day, 0, 0, 0, 0, 1, 0); // add 1 day
	}

	if ($weekinmonth < 0)
	{
		$weekinmonth = (count($month[$wday]) - abs($weekinmonth) + 1);
	}
	$dayinmonth = (!empty($month[$wday][$weekinmonth]) ? $month[$wday][$weekinmonth] : 0);

	return($dayinmonth);
}

// --------------------------------------------------------------------------------

// Get repeating dates by year

function local_byYear($startdate, $enddate)
{
	global $rr_debug, $rr_byyear, $rr_bymonth, $rr_byday, $rr_dates;
	
	if ($rr_debug >= 2)
	{
		// !@test byYear
		print("\n> local_byYear");
	}

	$cnt = 0;
	if (count($rr_byyear) > 0)
	{
		$cnt++;
		foreach ($rr_byyear as $year)
		{
			$t = getdate($startdate);
			$wdate = mktime($t[hours], $t[minutes], $t[seconds], $t[month], $t[mday], $year);
			if ($startdate <= $wdate && $wdate < $enddate && !local_maxDates())
			{
				if ($rr_debug >= 2)
				{
					// !@test local_byYear : wdate
					print("\nlocal_byYear : => wdate: " . date('d-m-Y H:i:s, D W', $wdate));
				}
				
				if (count($rr_bymonth) == 0 && count($rr_byday) > 0)
				{
					$cnt = local_byDay($wdate, $enddate);
				}
				else
				{
					$cnt = local_byYearDay($wdate, $enddate);
				}
				if ($cnt == 0) 
				{
					$rr_dates[] = $wdate;
					$cnt++;
				}
			}
		}
	}
	elseif (!local_maxDates())
	{
		$cnt = local_byYearDay($startdate, $enddate);
	}

	if ($rr_debug >= 2)
	{
		// !@test end byYear
		print("\n< local_byYear");
	}

	return ($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by month day
function local_byYearDay($startdate, $enddate)
{
	global $rr_debug, $rr_byyearday, $rr_dates;
	
	if ($rr_debug >= 2)
	{
		print("\n>> local_byYearDay"); // !@test byYearDay
	}

	$cnt = 0;
	if (count($rr_byyearday) > 0)
	{
		$cnt++;
		foreach ($rr_byyearday as $day)
		{
			$t = getdate($startdate);
			$wdate = mktime($t['hours'], $t['minutes'], $t['seconds'], 1, $day, $t['year']);
			if ($startdate <= $wdate && $wdate < $enddate && !local_maxDates())
			{
				if ($rr_debug >= 2)
				{
					print("\nlocal_byYearDay : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byYearDay : wdate
				}
				
				$cnt = local_byMonth($wdate, $enddate);
				if ($cnt == 0)
				{
					$rr_dates[] = $wdate;
					$cnt++;
				}
			}
		}
	}
	elseif (!local_maxDates())
	{
		$cnt = local_byMonth($startdate, $enddate);
	}
	
	if ($rr_debug >= 2)
	{
		print("\n<< local_byYearDay"); // !@test end byYearDay
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by month
function local_byMonth($startdate, $enddate)
{
	global $rr_debug, $rr_bymonth, $rr_dates;

	if ($rr_debug >= 2)
	{
		print("\n>>> local_byMonth"); // !@test byMonth
	}

	$cnt = 0;
	if (count($rr_bymonth) > 0)
	{
		$cnt++;
		foreach ($rr_bymonth as $month)
		{
			$t = getdate($startdate);
			$wdate = mktime($t["hours"], $t["minutes"], $t["seconds"], $month, $t["mday"], $t["year"]);
			if ($startdate <= $wdate && $wdate < $enddate && !local_maxDates())
			{
				if ($rr_debug >= 2)
				{
					print("\nlocal_byMonth : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byMonth : wdate
				}
				
				$cnt = local_byMonthDay($wdate, $enddate);
				if ($cnt == 0)
				{
					$rr_dates[] = $wdate;
					$cnt++;
				}
			}
		}
	}
	elseif(!local_maxDates())
	{
		$cnt = local_byMonthDay($startdate, $enddate);
	}

	if ($rr_debug >= 2)
	{
		print("\n<<< local_byMonth"); // !@test end byMonth
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by month day
function local_byMonthDay($startdate, $enddate)
{
	global $rr_debug, $rr_bymonthday, $rr_dates;
	
	if ($rr_debug >= 2)
	{
		print("\n>>>> local_byMonthDay"); // !@test byMonthDay
	}

	$cnt = 0;
	if (count($rr_bymonthday) > 0)
	{
		$cnt++;
		foreach ($rr_bymonthday as $day)
		{
			$day = intval($day);
			if ($day <= 0)
			{
				$wdate = local_addDate($startdate, 0, 0, 0, 1, 0, 0);
				$t = getdate($wdate);
				$wdate = mktime($t["hours"], $t["minutes"], $t["seconds"], $t["mon"], ($day + 1), $t["year"]);
			}
			else
			{
				$t = getdate($startdate);
				$wdate = mktime($t['hours'], $t['minutes'], $t['seconds'], $t['mon'], $day, $t['year']);				
			}

			if ($rr_debug >= 2)
			{
				print("\nlocal_byMonthDay : " . '$day, $wdate, $enddate : ' . $day . ', ' . date('d-m-Y H:i:s', $wdate) . ', ' . date('d-m-Y H:i:s', $enddate)); // !@test day, wdate, enddate
			}
			
			$t = getdate($wdate);
			if ($day <= 0 || ($day > 0 && $day == intval($t["mday"])))
			{
				if ($startdate <= $wdate && $wdate <= $enddate && !local_maxDates())
				{
					$wenddate = local_addDate($wdate, 0, 0, 0, 0, 1, 0);
					
					if ($rr_debug >= 2)
					{
						print("\nlocal_byMonthDay : " . '$day, $wdate, $wenddate : ' . $day . ', ' . date('d-m-Y H:i:s', $wdate) . ', ' . date('d-m-Y H:i:s', $enddate)); // !@test day, wdate, wenddate
					}
					
					$cnt = local_byDay($wdate, $wenddate);
					if ($cnt == 0)
					{
						$rr_dates[] = $wdate;
						$cnt++;
					}
				}
			}
		}
	}
	elseif (!local_maxDates())
	{
		$cnt = local_byDay($startdate, $enddate);
	}
	
	if ($rr_debug >= 2)
	{
		print("\n<<<< local_byMonthDay"); // !@test end byMonthDay
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by day
function local_byDay($startdate, $enddate)
{
	global $rr_debug, $rr_freq, $rr_byday, $rr_dates, $rr_sdays, $rr_idays, $rr_wkst;
	
	if ($rr_debug >= 2)
	{
		print("\n>>>>> local_byDay"); // !@test byDay
	}

	$wxf = ($rr_wkst ? 'N' : 'w');
	
	$cnt = 0;
	if (count($rr_byday) > 0)
	{
		//if (empty($rr_byday[0]))
		//{
		//	$rr_byday[0] = $rr_idays[date($wxf, $startdate)];
		//}

		$cnt++;
		foreach ($rr_byday as $tday)
		{
			$dc = 999;
			$sday = substr($tday, -2);
			if (strlen($sday) < 2)
			{
				// missing start day, use current date for DOW
				$sday = $rr_idays[date($wxf, $startdate)];
			}
			elseif (strlen($tday) > 2)
			{
				$dc = substr($tday, 0, strlen($tday) - 2);
			}

			if ($rr_freq == 'y')
			{
				$day = $rr_sdays[$sday];
				
				$t = getdate($startdate);
				$date1 = mktime($t['hours'], $t['minutes'], $t['seconds'], 1, 1, $t['year']);
				$date2 = mktime($t['hours'], $t['minutes'], $t['seconds'], 12, 31, $t['year']);
				
				$fd = 0; // fromdate = startdate 
				if ($dc < 0)
				{
					$dc = abs($dc);
					$fd = 1;  // fromdate = enddate 
				}
				while ($date1 <= $date2)
				{
					$wdate = ($fd ? $date2 : $date1);
					$wd = date($wxf, $wdate);
					if ($wd == $day)
					{
						if ($dc == 1 || $dc > 500)
						{
							if ($startdate <= $wdate && $wdate < $enddate && !local_maxDates())
							{
								if ($rr_debug >= 2)
								{
									print("\nlocal_byDay/Y : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byDay : wdate
								}
								
								$cnt = local_byMonthNo($wdate, $enddate);
								if ($cnt == 0)
								{
									$rr_dates[] = $wdate;
									$cnt++;
								}
							}
							if ($dc == 1)
							{
								break;
							}
						}
						$dc--;
					}
					
					if ($fd)
					{
						$date2 = local_addDate($date2, 0, 0, 0, 0, -1, 0);
					}
					else
					{
						$date1 = local_addDate($date1, 0, 0, 0, 0, 1, 0);
					}
				}
			}
			elseif ($rr_freq != 'w')
			{
				$imin = 1;
				$imax = 5; // max # of occurances in a month
				if (strlen($tday) > 2)
				{
					$imin = $imax = $dc;
				}

				for ($i = $imin; $i <= $imax; $i++)
				{
					$wdate = local_getDateFromDay($startdate, $i-1, $rr_sdays[$sday]);
					if ($wdate && $startdate <= $wdate && $wdate < $enddate && !local_maxDates())
					{
						if ($rr_debug >= 2)
						{
							print("\nlocal_byDay/* : i=$i, tday=$tday => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byDay : wdate
						}
						
						$cnt = local_byWeekNo($wdate, $enddate);
						if ($cnt == 0)
						{
							$rr_dates[] = $wdate;
							$cnt++;
							//break;
						}
					}
				}
			}
			else
			{
				// day of week version
				$startdate_dow = date($wxf, $startdate);
				$datedelta = $rr_sdays[$sday] - $startdate_dow;

				if ($datedelta >= 0)
				{
					$wdate = local_addDate($startdate, 0, 0, 0, 0, $datedelta, 0);
					if ($startdate <= $wdate && $wdate < $enddate && !local_maxDates())
					{
						if ($rr_debug >= 2)
						{
							print("\nlocal_byDay/W : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byDay : wdate
						}
						
						$cnt = local_byWeekNo($wdate, $enddate);
						if ($cnt == 0)
						{
							$rr_dates[] = $wdate;
							$cnt++;
						}
					}
				}
			}
		}
	}
	else if(!local_maxDates())
	{
		$cnt = local_byWeekNo($startdate, $enddate);
	}
	
	if ($rr_debug >= 2)
	{
		print("\n<<<<< local_byDay"); // !@test end byDay
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by month number
function local_byMonthNo($startdate, $enddate)
{
	global $rr_debug, $rr_bymonth, $rr_dates;

	if ($rr_debug >= 2)
	{
		print("\n>>>>>> local_byMonthNo"); // !@test local_byMonthNo
	}

	$cnt = 0;
	if (count($rr_bymonth) > 0)
	{
		$wdate = $startdate;
		
		$cnt++;
		foreach ($rr_bymonth as $month)
		{
			$mn = date('m', $wdate);
			if ($mn == $month && $wdate < $enddate && !local_maxDates())
			{
				if ($rr_debug >= 2)
				{
					print("\nlocal_byMonthNo : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byMonthNo : wdate
				}
				
				$cnt = local_byWeekNo($wdate, $enddate);
				if ($cnt == 0)
				{
					$rr_dates[] = $wdate;
					$cnt++;
				}
			}
		}
	}
	elseif (!local_maxDates())
	{
		$cnt = local_byWeekNo($startdate, $enddate);
	}

	if ($rr_debug >= 2)
	{
		print("\n<<<<<< local_byMonthNo"); // !@test end local_byMonthNo
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by week number
function local_byWeekNo($startdate, $enddate)
{
	global $rr_debug, $rr_byweekno, $rr_dates;

	if ($rr_debug >= 2)
	{
		print("\n>>>>>>> local_byWeekNo"); // !@test local_byWeekNo
	}

	$cnt = 0;
	if (count($rr_byweekno) > 0)
	{
		$wdate = $startdate;
		
		$cnt++;
		foreach ($rr_byweekno as $week)
		{
			$wn = date('W', $wdate);
			if ($wn == $week && $wdate < $enddate && !local_maxDates())
			{
				if ($rr_debug >= 2)
				{
					print("\nlocal_byWeekNo : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byWeekNo : wdate
				}
				
				$cnt = local_byHour($wdate, $enddate);
				if ($cnt == 0)
				{
					$rr_dates[] = $wdate;
					$cnt++;
				}
			}
		}
	}
	elseif (!local_maxDates())
	{
		$cnt = local_byHour($startdate, $enddate);
	}

	if ($rr_debug >= 2)
	{
		print("\n<<<<<<< local_byWeekNo"); // !@test end local_byWeekNo
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by hour
function local_byHour($startdate, $enddate)
{
	global $rr_debug, $rr_byhour, $rr_dates;

	if ($rr_debug >= 2)
	{
		print("\n>>>>>>>> local_byHour"); // !@test local_byHour
	}

	$cnt = 0;
	if (count($rr_byhour) > 0)
	{
		$cnt++;
		foreach ($rr_byhour as $hour)
		{
			$t = getdate($startdate);
			$wdate = mktime($hour, $t["minutes"], $t["seconds"], $t["mon"], $t["mday"], $t["year"]);
			if ($startdate <= $wdate && $wdate < $enddate && !local_maxDates())
			{
				if ($rr_debug >= 2)
				{
					print("\nlocal_byHour : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byHour : wdate
				}
				
				$cnt = local_byMinute($wdate, $enddate);
				if ($cnt == 0)
				{
					$rr_dates[] = $wdate;
					$cnt++;
				}
			}
		}
	}
	elseif (!local_maxDates())
	{
		$cnt = local_byMinute($startdate, $enddate);
	}

	if ($rr_debug >= 2)
	{
		print("\n<<<<<<<< local_byHour"); // !@test end local_byHour
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by minute
function local_byMinute($startdate, $enddate)
{
	global $rr_debug, $rr_byminute, $rr_dates;

	if ($rr_debug >= 2)
	{
		print("\n>>>>>>>>> local_byMinute"); // !@test local_byMinute
	}

	$cnt = 0;
	if (count($rr_byminute) > 0)
	{
		$cnt++;
		foreach ($rr_byminute as $minute)
		{
			$t = getdate($startdate);
			$wdate = mktime($t["hours"], $minute, $t["seconds"], $t["mon"], $t["mday"], $t["year"]);
			if ($startdate <= $wdate && $wdate < $enddate && !local_maxDates())
			{
				if ($rr_debug >= 2)
				{
					print("\nlocal_byMinute : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_byMinute : wdate
				}
				
				$cnt = local_bySecond($wdate, $enddate);
				if ($cnt == 0)
				{
					$rr_dates[] = $wdate;
					$cnt++;
				}
			}
		}
	}
	elseif (!local_maxDates())
	{
		$cnt = local_bySecond($startdate, $enddate);
	}

	if ($rr_debug >= 2)
	{
		print("\n<<<<<<<<< local_byMinute"); // !@test end local_byMinute
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

// Get repeating dates by second
function local_bySecond($startdate, $enddate)
{
	global $rr_debug, $rr_bysecond, $rr_dates;

	if ($rr_debug >= 2)
	{
		print("\n>>>>>>>>>> local_bySecond"); // !@test local_bySecond
	}

	$cnt = 0;
	if (count($rr_bysecond) > 0)
	{
		$cnt++;
		foreach ($rr_bysecond as $second)
		{
			$t = getdate($startdate);
			$wdate = mktime($t["hours"], $t["minutes"], $second, $t["mon"], $t["mday"], $t["year"]);
			if ($startdate <= $wdate && $wdate < $enddate && !local_maxDates())
			{
				if ($rr_debug >= 2)
				{
					print("\nlocal_bySecond : => wdate: " . date('d-m-Y H:i:s, D W', $wdate)); // !@test local_bySecond : wdate
				}
				
				$rr_dates[] = $wdate;
				$cnt++;
			}
		}
	}

	if ($rr_debug >= 2)
	{
		print("\n<<<<<<<<<< local_bySecond"); // !@test end local_bySecond
	}

	return($cnt);
}

// --------------------------------------------------------------------------------

function local_sortByDay()
{
	global $rr_byday, $rr_sdays;

	sort($rr_byday);	// minus signed days first
	
	$retval = array();
	foreach ($rr_byday as $day)
	{
		$key = ((1 + intval($rr_sdays[substr($day, -2)])) * 10);
		while (isset($retval[$key]))
		{
			$key++;
		}
		$retval[$key] = $day;
	}
	
	ksort($retval);
	$rr_byday = $retval;
}

// --------------------------------------------------------------------------------

function local_getDates($startdate, $maxdate = null, $rules = null)
{
	global $rr_debug, $rr_dates, $rr_repeatmode, $rr_until, $rr_freq, $rr_count, $rr_interval, $rr_wkst, $rr_bysecond, $rr_byminute, $rr_byhour, $rr_byweekno, $rr_byday, $rr_bymonthday, $rr_bymonth, $rr_byyearday, $rr_byyear, $rr_exdates, $rr_sdays, $rr_idays;
	
	$rr_repeatmode = null;

	$rr_until = null;
	$rr_freq = null;
	$rr_count = 0;
	$rr_interval = 1;
	$rr_wkst = 0;	// Default non ISO: 0=Sunday

	$rr_bysecond = array();
	$rr_byminute = array();
	$rr_byhour = array();
	$rr_byweekno = array();
	$rr_byday = array();
	$rr_bymonthday = array();
	$rr_bymonth = array();
	$rr_byyearday = array();
	$rr_byyear = array();

	$rr_exdates = array(); // array of exception dates
	$rr_dates = array(); // array with resulting rrule dates

	if (empty($rules))
	{
		return('');
	}

	$rules = str_replace("\'", '', $rules);
	$rules = str_replace('RRULE:', '', $rules);
	$rules = explode(";", $rules);
	$ruletype = "";

	$nextdate = $enddate = $startdate;
	$done = false;
	$eventcount = 0;
	$loopcount = 0;

	foreach($rules as $rule)
	{
		$item = explode("=", $rule);
		switch ($item[0])
		{
			case "FREQ":
				switch ($item[1])
				{
					case "MINUTELY":
						$rr_freq = "i";
						break;
					default:
						$rr_freq = strtolower($item[1][0]);
						break;
				}
				break;
			case "INTERVAL":
				$rr_interval = $item[1];
				break;
			case "BYSECOND":
				$rr_bysecond = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "BYMINUTE":
				$rr_byminute = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "BYHOUR":
				$rr_byhour = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "BYWEEKNO":
				$rr_byweekno = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "BYDAY":
				$rr_byday = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "BYMONTHDAY":
				$rr_bymonthday = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "BYMONTH":
				$rr_bymonth = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "BYYEARDAY":
				$rr_byyearday = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "BYYEAR":
				$rr_byyear = explode(",", $item[1]);
				$ruletype = $item[0];
				break;
			case "COUNT":
				$rr_count = intval($item[1]);
				$rr_repeatmode = "c";
				break;
			case "WKST":
				$rr_wkst = ($item[1] == 'MO' ? 1 : 0);
				break;
			case "UNTIL":
				// UNTIL=20071104
				// UNTIL=20071104T020000
				// UNTIL=20170327T160000Z
				$zzVAR1 = explode('Z', $item[1]);
				$zzVAR1 = str_replace('T', '', $zzVAR1[0]);
				$rr_until = strtotime($zzVAR1 . (strlen($zzVAR1) == 8 ? '235959' : ''));
				$rr_repeatmode = "u";
				break;
		}
	}

	if ($rr_debug)
	{
		// !@test >>
		print("rules :\n");print_r($rules);
		print("\nruletype = $ruletype");

		print("\nrepeatmode = $rr_repeatmode");

		print("\nuntil = $rr_until : " . date('d-m-Y H:i:s', $rr_until));
		print("\nfreq = $rr_freq");
		print("\ncount = $rr_count");
		print("\ninterval = $rr_interval");
		print("\nwkst = $rr_wkst");

		print("\nbysecond :\n");print_r($rr_bysecond);
		print("\nbyminute :\n");print_r($rr_byminute);
		print("\nbyhour :\n");print_r($rr_byhour);
		print("\nbyweekno :\n");print_r($rr_byweekno);
		print("\nbyday :\n");print_r($rr_byday);
		print("\nbymonthday :\n");print_r($rr_bymonthday);
		print("\nbymonth :\n");print_r($rr_bymonth);
		print("\nbyyear :\n");print_r($rr_byyear);
	}
	
	$wxf = ($rr_wkst > 0 ? 'N' : 'w');
	$rr_sdays = array("SU" => ($rr_wkst > 0 ? 7 : 0), "MO" => 1, "TU" => 2, "WE" => 3, "TH" => 4, "FR" => 5, "SA" => 6);
	asort($rr_sdays);
	$rr_idays = array_flip($rr_sdays);
	
	if (count($rr_byday) > 0)
	{
		local_sortByDay();
	}
	
	while (!$done)
	{
		switch ($rr_freq)
		{
			case "y":
				if ($eventcount > 0)
				{
					$nextdate = local_addDate($nextdate, 0, 0, 0, 0, 0, $rr_interval);
					if (!empty($rr_byday))
					{
						$t = getdate($nextdate);
						$nextdate = mktime($t["hours"], $t["minutes"], $t["seconds"], $t["mon"], 1, $t["year"]);
					}
					if (!empty($rr_byyearday))
					{
						$t = getdate($nextdate);
						$nextdate = mktime($t["hours"], $t["minutes"], $t["seconds"], 1, 1, $t["year"]);
					}
					if (!empty($rr_bymonth))
					{
						$t = getdate($nextdate);
						$nextdate = mktime($t["hours"], $t["minutes"], $t["seconds"], 1, $t["mday"], $t["year"]);
					}
				}
				$enddate = local_addDate($nextdate, 0, 0, 0, 0, 0, 1);
				break;

			case "m":
				if ($eventcount > 0)
				{
					$nextdate = local_addDate($nextdate, 0, 0, 0, $rr_interval, 0, 0);
					if (!empty($rr_bymonthday))
					{
						$t = getdate($nextdate);
						$nextdate = mktime($t["hours"], $t["minutes"], $t["seconds"], $t["mon"], 1, $t["year"]);
					}
				}
				if (!empty($rr_byday))
				{
					$t = getdate($nextdate);
					if ($t["mday"] > 28)
					{
						//check for short months when using month by day, make sure we do not overshoot the counter and skip a month
						$nextdate = local_addDate($nextdate, 0, 0, 0, $rr_interval, 0, 0);
						$t2 = getdate($nextdate);
						if ($t2["mday"] < $t["mday"])
						{
							// oops, skipped a month, backup to previous month
							$nextdate = local_addDate($nextdate, 0, 0, 0, 0, ($t2["mday"] - $t["mday"]), 0);
						}
					}
					$t = getdate($nextdate);
					$nextdate = mktime($t["hours"], $t["minutes"], $t["seconds"], $t["mon"], 1, $t["year"]);
				}
				$enddate = local_addDate($nextdate, 0, 0, 0, 1, 0, 0);
				if (!empty($rr_bymonthday))
				{
					$t = getdate($enddate);
					$enddate = mktime($t["hours"], $t["minutes"], $t["seconds"], $t["mon"], 0, $t["year"]);
				}
				break;

			case "w":
				if ($eventcount > 0)
				{
					$nextdate = local_addDate($nextdate, 0, 0, 0, 0, ($rr_interval * 7), 0);
					if (!empty($rr_byday))
					{
						$dow = date($wxf, $nextdate);
						// move to beginning of week
						$diff = $rr_wkst - $dow;
						if ($diff > 0)
						{
							$diff = ($diff - 7);
						}
						$nextdate = local_addDate($nextdate, 0, 0, 0, 0, $diff, 0);
					}
				}
				$enddate = local_addDate($nextdate, 0, 0, 0, 0, 7, 0);
				break;

			case "d":
				$nextdate = ($eventcount == 0 ? $nextdate : local_addDate($nextdate, 0, 0, 0, 0, $rr_interval, 0));
				$enddate = local_addDate($nextdate, 0, 0, 0, 0, 1, 0);
				break;

			case "h":
				$nextdate = ($eventcount == 0 ? $nextdate : local_addDate($nextdate, $rr_interval, 0, 0, 0, 0, 0));
				$enddate = local_addDate($nextdate, 1, 0, 0, 0, 0, 0);
				break;

			case "i":
				$nextdate = ($eventcount == 0 ? $nextdate : local_addDate($nextdate, 0, $rr_interval, 0, 0, 0, 0));
				$enddate = local_addDate($nextdate, 0, 1, 0, 0, 0, 0);
				break;
		}

		if ($rr_debug)
		{
			// !@test local_getDates : next-/enddate, eventcount
			print("\nlocal_getDates : " . '$nextdate, $enddate : ' . date('d-m-Y H:i:s', $nextdate) . ', ' . date('d-m-Y H:i:s', $enddate));
		}
		
		if ($maxdate > 0 && $nextdate > $maxdate)
		{
			if (count($rr_dates) > 0)
			{
				while (count($rr_dates) > 0 && end($rr_dates) > $maxdate)
				{
					array_pop($rr_dates);
				}
			}
			
			if ($rr_debug)
			{
				print("\nlocal_getDates @1: ends here\n"); //@test local_getDates : @1end
			}
			
			$done = true;
		}
		else
		{
			$cnt = local_byYear($nextdate, $enddate);
			$eventcount += $cnt;
		}

		if (!$done)
		{
			if (local_maxDates())
			{
				if ($rr_debug)
				{
					print("\nlocal_getDates @2: ends here\n"); //@test local_getDates : @2end
				}
				
				$done = true;
			}
			elseif ($cnt == 0)
			{
				$rr_dates[] = $nextdate;
				$eventcount++;
			}

			$year = date("Y", $nextdate);
			if ($year > _ZAPCAL_MAXYEAR)
			{
				if ($rr_debug)
				{
					print("\nlocal_getDates @3: ends here\n"); //@test local_getDates : @3end
				}
				
				$done = true;
			}
			
			$loopcount++;
			if ($loopcount > _ZAPCAL_MAXYEAR)
			{
				$done = true;
				//throw new Exception("Infinite loop detected in getDates()");
			}
		}
	}

	if ($rr_repeatmode == "u")
	{
		while (end($rr_dates) > $rr_until)
		{
			array_pop($rr_dates);
		}
	}

	$rr_dates = array_unique($rr_dates);

	if (count($rr_exdates) > 0)
	{
		foreach ($rr_exdates as $exdate)
		{
			if ($pos = array_search($exdate, $rr_dates))
			{
				array_splice($rr_dates, $pos, 1);
				$excount++;
			}
		}
	}

	sort($rr_dates);
}

// --------------------------------------------------------------------------------

/*
Following rule tests are from the examples page:
https://icalendar.org/iCalendar-RFC-5545/3-8-5-3-recurrence-rule.html

Remove the 'x' after the $ to test a fromdate/todate with a specific rule
*/

$fromdate = 19970101;
$todate = 19971231;

// Daily for 10 occurrences:
// 1997 September 2-11.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=DAILY;COUNT=10', 'END:VEVENT');

// Daily until December 24, 1997:
// 1997 September 2-30;
// 1997 October 1-31;
// 1997 November 1-30;
// 1997 December 1-23.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=DAILY;UNTIL=19971224T000000Z', 'END:VEVENT');

// Every other day - forever:
// 1997 September 2,4,6,8...24,26,28,30;
// 1997 October 2,4,6...20,22,24,26,28,30;
// 1997 November 1,3,5,7...25,27,29;
// 1997 December 1,3,...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=DAILY;INTERVAL=2', 'END:VEVENT');

// Every 10 days, 5 occurrences:
// 1997 September 2,12,22;
// 1997 October 2,12.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=DAILY;INTERVAL=10;COUNT=5', 'END:VEVENT');

$xfromdate = 19980101;
$xtodate = 20001231;

// Every day in January, for 3 years:
// 1998 January 1-31;
// 1999 January 1-31;
// 2000 January 1-31.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19980101T090000', 'RRULE:FREQ=YEARLY;UNTIL=20000131T140000Z;BYMONTH=1;BYDAY=SU,MO,TU,WE,TH,FR,SA', 'END:VEVENT');

// Or, also every day in January, for 3 years:
// 1998 January 1-31;
// 1999 January 1-31;
// 2000 January 1-31.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19980101T090000', 'RRULE:FREQ=DAILY;UNTIL=20000131T140000Z;BYMONTH=1', 'END:VEVENT');

$xfromdate = 19970101;
$xtodate = 19981231;

// Weekly for 10 occurrences:
// 1997 September 2,9,16,23,30;
// 1997 October 7,14,21,28;
// 1997 November 4.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=WEEKLY;COUNT=10', 'END:VEVENT');

// Weekly until December 24, 1997:
// 1997 September 2,9,16,23,30;
// 1997 October 7,14,21,28;
// 1997 November 4,11,18,25;
// 1997 December 2,9,16,23.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=WEEKLY;UNTIL=19971224T000000Z', 'END:VEVENT');

// Every other week - forever
// 1997 September 2,16,30;
// 1997 October 14,28;
// 1997 November 11,25;
// 1997 December 9,23;
// 1998 January 6,20;
// 1998 February 3, 17;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=WEEKLY;INTERVAL=2;WKST=SU', 'END:VEVENT');

// Weekly on Tuesday and Thursday for five weeks:
// 1997 September 2,4,9,11,16,18,23,25,30;
// 1997 October 2.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=WEEKLY;UNTIL=19971007T000000Z;WKST=SU;BYDAY=TU,TH', 'END:VEVENT');

// Or, also weekly on Tuesday and Thursday for five weeks:
// 1997 September 2,4,9,11,16,18,23,25,30;
// 1997 October 2.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=WEEKLY;COUNT=10;WKST=SU;BYDAY=TU,TH', 'END:VEVENT');

// Every other week on Monday, Wednesday, and Friday until December 24, 1997, starting on Monday, September 1, 1997:
// 1997 September 1,3,5,15,17,19,29;
// 1997 October 1,3,13,15,17,27,29,31;
// 1997 November 10,12,14,24,26,28;
// 1997 December 8,10,12,22.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970901T090000', 'RRULE:FREQ=WEEKLY;INTERVAL=2;UNTIL=19971224T000000Z;WKST=SU;BYDAY=MO,WE,FR', 'END:VEVENT');

// Every other week on Tuesday and Thursday, for 8 occurrences:
// 1997 September 2,4,16,18,30;
// 1997 October 2,14,16.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=8;WKST=SU;BYDAY=TU,TH', 'END:VEVENT');

// Monthly on the first Friday for 10 occurrences:
// 1997 September 5;
// 1997 October 3;
// 1997 November 7;
// 1997 December 5;
// 1998 January 2;
// 1998 February 6;
// 1998 March 6;
// 1998 April 3;
// 1998 May 1;
// 1998 June 5.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970905T090000', 'RRULE:FREQ=MONTHLY;COUNT=10;BYDAY=1FR', 'END:VEVENT');

// Monthly on the first Friday until December 24, 1997:
// 1997 September 5;
// 1997 October 3;
// 1997 November 7;
// 1997 December 5.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970905T090000', 'RRULE:FREQ=MONTHLY;UNTIL=19971224T000000Z;BYDAY=1FR', 'END:VEVENT');

// Every other month on the first and last Sunday of the month for 10 occurrences:
// 1997 September 7,28;
// 1997 November 2,30;
// 1998 January 4,25;
// 1998 March 1,29;
// 1998 May 3,31.
$icsdata = array('BEGIN:VEVENT', 'DTSTART:19970907T090000', 'RRULE:FREQ=MONTHLY;INTERVAL=2;COUNT=10;BYDAY=1SU,-1SU', 'END:VEVENT');

// Monthly on the second-to-last Monday of the month for 6 months:
// 1997 September 22;
// 1997 October 20;
// 1997 November 17;
// 1997 December 22;
// 1998 January 19;
// 1998 February 16.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970922T090000', 'RRULE:FREQ=MONTHLY;COUNT=6;BYDAY=-2MO', 'END:VEVENT');

// Monthly on the third day of the month, forever:
// 1997 October 3;
// 1997 November 3;
// 1997 December 3;
// 1997 January 3;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970928T090000', 'RRULE:FREQ=MONTHLY;BYMONTHDAY=3', 'END:VEVENT');

// Monthly on the third-to-the-last day of the month, forever:
// 1997 September 28;
// 1997 October 29;
// 1997 November 28;
// 1997 December 29;
// 1998 January 29;
// 1998 February 26;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970928T090000', 'RRULE:FREQ=MONTHLY;BYMONTHDAY=-3', 'END:VEVENT');

// Monthly on the 2nd and 15th of the month for 10 occurrences:
// 1997 September 2,15;
// 1997 October 2,15;
// 1997 November 2,15;
// 1997 December 2,15;
// 1998 January 2,15.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=MONTHLY;COUNT=10;BYMONTHDAY=2,15', 'END:VEVENT');

// Monthly on the first and last day of the month for 12 occurrences:
// 1997 September 30;
// 1997 October 1,31;
// 1997 November 1,30;
// 1997 December 1,31;
// 1998 January 1,31;
// 1998 February 1.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970930T090000', 'RRULE:FREQ=MONTHLY;COUNT=12;BYMONTHDAY=1,-1', 'END:VEVENT');

$xfromdate = 19970101;
$xtodate = 20061231;

// Every 18 months on the 10th thru 15th of the month for 10 occurrences:
// 1997 September 10,11,12,13,14,15;
// 1999 March 10,11,12,13.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970910T090000', 'RRULE:FREQ=MONTHLY;INTERVAL=18;COUNT=10;BYMONTHDAY=10,11,12,13,14,15', 'END:VEVENT');

// Every Tuesday, every other month:
// 1997 September 2,9,16,23,30;
// 1997 November 4,11,18,25;
// 1998 January 6,13,20,27;
// 1998 March 3,10,17,24,31;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=MONTHLY;INTERVAL=2;BYDAY=TU', 'END:VEVENT');

// Yearly in June and July for 10 occurrences:
// 1997 June 10;
// 1997 July 10;
// 1998 June 10;
// 1998 July 10;
// 1999 June 10;
// 1999 July 10;
// 2000 June 10;
// 2000 July 10;
// 2001 June 10;
// 2001 July 10.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970610T090000', 'RRULE:FREQ=YEARLY;COUNT=10;BYMONTH=6,7', 'END:VEVENT');

// Every other year on January, February, and March for 10 occurrences:
// 1997 March 10;
// 1999 January 10;
// 1999 February 10;
// 1999 March 10;
// 2001 January 10;
// 2001 February 10;
// 2001 March 10
// 2003 January 10;
// 2003 February 10;
// 2003 March 10.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970310T090000', 'RRULE:FREQ=YEARLY;INTERVAL=2;COUNT=10;BYMONTH=1,2,3', 'END:VEVENT');

// Every third year on the 1st, 100th, and 200th day for 10 occurrences:
// 1997 January 1;
// 1997 April 10;
// 1997 July 19;
// 2000 January 1
// 2000 April 9;
// 2000 July 18;
// 2003 January 1
// 2003 April 10;
// 2003 July 19;
// 2006 January 1.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970101T090000', 'RRULE:FREQ=YEARLY;INTERVAL=3;COUNT=10;BYYEARDAY=1,100,200', 'END:VEVENT');

$xtodate = 20001231;

// Every 20th Monday of the year, forever:
// 1997 May 19;
// 1998 May 18;
// 1999 May 17;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970519T090000', 'RRULE:FREQ=YEARLY;BYDAY=20MO', 'END:VEVENT');

// Every 20th Monday, 23rd Wednesday, 4th last Sunday of the year, forever:
// 1997 May 19;
// 1997 June 04;
// 1997 December 07;
// 1998 May 18;
// 1998 June 10;
// 1998 December 06;
// 1999 May 17;
// 1999 June 09;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970519T090000', 'RRULE:FREQ=YEARLY;BYDAY=20MO,23WE,-4SU', 'END:VEVENT');

// Monday of week number 20 (where the default start of the week is Monday), forever:
// 1997 May 12
// 1998 May 11;
// 1999 May 17;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970512T090000', 'RRULE:FREQ=YEARLY;BYWEEKNO=20;BYDAY=MO', 'END:VEVENT');

// Every Thursday in March, forever:
// 1997 March 13,20,27
// 1998 March 5,12,19,26;
// 1999 March 4,11,18,25;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970313T090000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=TH', 'END:VEVENT');

// Every Thursday, but only during June, July, and August, forever:
// 1997 June 5,12,19,26;
// 1997 July 3,10,17,24,31;
// 1997 August 7,14,21,28;
// 1998 June 4,11,18,25;
// 1998 July 2,9,16,23,30;
// 1998 August 6,13,20,27;
// 1999 June 3,10,17,24;
// 1999 July 1,8,15,22,29;
// 1999 August 5,12,19,26;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970605T090000', 'RRULE:FREQ=YEARLY;BYDAY=TH;BYMONTH=6,7,8', 'END:VEVENT');

// Every Friday the 13th, forever:
// 1998 February 13;
// 1998 March 13;
// 1998 November 13;
// 1999 August 13;
// 2000 October 13;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=MONTHLY;BYDAY=FR;BYMONTHDAY=13', 'END:VEVENT');

// The first Saturday that follows the first Sunday of the month, forever:
// 1997 September 13;
// 1997 October 11;
// 1997 November 8;
// 1997 December 13;
// 1998 January 10;
// 1998 February 7;
// 1998 March 7;
// 1998 April 11;
// 1998 May 9;
// 1998 June 13;...
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970913T090000', 'RRULE:FREQ=MONTHLY;BYDAY=SA;BYMONTHDAY=7,8,9,10,11,12,13', 'END:VEVENT');

$xfromdate = 19960101;
$xtodate = 20051231;

// Every 4 years, the first Tuesday after a Monday in November, forever (U.S. Presidential Election day):
// 1996 November 5;
// 2000 November 7;
// 2004 November 2;
// ...
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19961105T090000', 'RRULE:FREQ=YEARLY;INTERVAL=4;BYMONTH=11;BYDAY=TU;BYMONTHDAY=2,3,4,5,6,7,8', 'END:VEVENT');

$xfromdate = 19970101;
$xtodate = 19981231;

// Every 3 hours from 9:00 AM to 5:00 PM on a specific day:
// 09:00; 12:00; 15:00.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=HOURLY;INTERVAL=3;UNTIL=19970902T170000Z', 'END:VEVENT');

// Every 15 minutes for 6 occurrences:
// 09:00; 09:15; 09:30; 09:45; 10:00; 10:15.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=MINUTELY;INTERVAL=15;COUNT=6', 'END:VEVENT');

// Every hour and a half for 4 occurrences:
// 09:00; 10:30; 12:00; 13:30.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=MINUTELY;INTERVAL=90;COUNT=4', 'END:VEVENT');

$xfromdate = 19970101;
$xtodate = 19970930;

// Every 20 minutes from 9:00 AM to 4:40 PM every day:
// 9:00; 9:20; 9:40; ...; 16:00; 16:20; 16:40.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=DAILY;BYHOUR=9,10,11,12,13,14,15,16;BYMINUTE=0,20,40', 'END:VEVENT');

// Or, also every 20 minutes from 9:00 AM to 4:40 PM every day:
// 9:00; 9:20; 9:40; ...; 16:00; 16:20; 16:40.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970902T090000', 'RRULE:FREQ=MINUTELY;INTERVAL=20;BYHOUR=9,10,11,12,13,14,15,16', 'END:VEVENT');

$xfromdate = 19970101;
$xtodate = 19981231;

// An example where the days generated makes a difference because of WKST:
// 1997 August 5,10,19,24.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970805T090000', 'RRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=4;BYDAY=TU,SU;WKST=MO', 'END:VEVENT');

// changing only WKST from MO to SU, yields different results...
// 1997 August 5,17,19,31.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:19970805T090000', 'RRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=4;BYDAY=TU,SU;WKST=SU', 'END:VEVENT');

$xfromdate = 20070101;
$xtodate = 20071231;

// An example where an invalid date (i.e., February 30) is ignored.
// 2007 January 15,30;
// 2007 February 15;
// 2007 March 15,30.
$xicsdata = array('BEGIN:VEVENT', 'DTSTART:20070115T090000', 'RRULE:FREQ=MONTHLY;BYMONTHDAY=15,30;COUNT=5', 'END:VEVENT');

/*
Het rrule-event:
*/

$fromdate = 20250101;
$todate = 20251231;

$xicsdata = array(
'BEGIN:VEVENT',
'CREATED:20250108T152137Z',
'DESCRIPTION:Acc: NL86INGB0012345678\nMark: 7123456789050001\nAmount: 
 123\,45 euro (10 terms)',
'DTEND;TZID=Europe/Amsterdam:20250227T110000',
'DTSTAMP:20250113T083518Z',
'DTSTART;TZID=Europe/Amsterdam:20250227T100000',
'LAST-MODIFIED:20250108T152137Z',
'RRULE:FREQ=MONTHLY;COUNT=10',
'SEQUENCE:0',
'SUMMARY:IRS: Pay IT 2025 (term)',
'TRANSP:OPAQUE',
'UID:D38DDE0E-BD54-4334-B3F8-78BC190B933E',
'X-APPLE-CREATOR-IDENTITY:com.apple.calendar',
'X-APPLE-CREATOR-TEAM-IDENTITY:0000000000',
'BEGIN:VALARM',
'ACTION:DISPLAY',
'DESCRIPTION:Reminder',
'TRIGGER:-PT15M',
'UID:5D5F7ADD-AF55-4596-BA30-809E75CCF63D',
'X-WR-ALARMUID:5D5F7ADD-AF55-4596-BA30-809E75CCF63D',
'END:VALARM',
'END:VEVENT',

'BEGIN:VEVENT',
'CREATED:20250113T083414Z',
'DESCRIPTION:Acc: NL86INGB0012345678\nMark: 7123456789050001\nAmount: 
 123\,45 euro (10 terms)',
'DTEND;TZID=Europe/Amsterdam:20251027T110000',
'DTSTAMP:20250113T083518Z',
'DTSTART;TZID=Europe/Amsterdam:20251027T100000',
'LAST-MODIFIED:20250113T083414Z',
'RECURRENCE-ID;TZID=Europe/Amsterdam:20251027T100000',
'SEQUENCE:0',
'SUMMARY:IRS: Pay IT 2025 (last term)',
'TRANSP:OPAQUE',
'UID:D38DDE0E-BD54-4334-B3F8-78BC190B933E',
'X-APPLE-CREATOR-IDENTITY:com.apple.calendar',
'X-APPLE-CREATOR-TEAM-IDENTITY:0000000000',
'BEGIN:VALARM',
'ACTION:DISPLAY',
'DESCRIPTION:Reminder',
'TRIGGER:-PT15M',
'UID:5D5F7ADD-AF55-4596-BA30-809E75CCF63D',
'X-WR-ALARMUID:5D5F7ADD-AF55-4596-BA30-809E75CCF63D',
'END:VALARM',
'BEGIN:VALARM',
'ACTION:NONE',
'TRIGGER;VALUE=DATE-TIME:19760401T005545Z',
'END:VALARM',
'END:VEVENT'
);


// ------------- START --------------

$rr_debug = 1;
$rr_debug = 2;
//$rr_debug = 0;

$fromdate = strtotime($fromdate . (strlen($fromdate) == 8 ? '000000' : ''));
$todate = strtotime($todate . (strlen($todate) == 8 ? '235959' : ''));

if ($rr_debug)
{
	print_r($icsdata); // !@test icsdata
}

$in00 = false; // VEVENT
$events = array();
$thisevent = array();

foreach ($icsdata as $lx => $icsevent)
{
	if ($icsevent == 'BEGIN:VEVENT')
	{
		$in00 = 1;	// Process this event
		$in01 = 1;	// Process this event until intermediate event
		$thisevent = array();
	}
	elseif ($icsevent == 'BEGIN:VALARM')
	{
		$in01 = 0;
	}
	elseif ($icsevent == 'END:VALARM')
	{
		$in01 = 1;
	}
	elseif ($in00 && $in01)
	{
		if ($icsevent == 'END:VEVENT')
		{
			$in00 = 0;
			if (!empty($thisevent['dtstart']) && $thisevent['dtstart'] <= $todate)
			{
				if (!empty($thisevent['rrule']))
				{
					if ($rr_debug)
					{
						// !@test >>
						print("\nfromdate = $fromdate : " . date('d-m-Y H:i:s', $fromdate));
						print("\ntodate   = $todate   : " . date('d-m-Y H:i:s', $todate));
						print("\ndtstart  = " . $thisevent['dtstart'] . '   : ' . date('d-m-Y H:i:s', $thisevent['dtstart']));
					}
					
					// !@note RRULE parser
					local_getDates($thisevent['dtstart'], $todate, $thisevent['rrule']);

					foreach ($rr_dates as $dtval)
					{
						if ($rr_debug)
						{
							print(date('d-m-Y H:i:s, D W', $dtval) . "\n");
						}
						
						if ($dtval >= $fromdate && $dtval <= $todate)
						{
							$thisevent['dtstart'] = $dtval;
						
							$events[] = $thisevent;
						}
						
						unset($thisevent['rrule']);
					}
				}
				elseif (!empty($thisevent['dtend']))
				{
					if ($thisevent['dtend'] >= $fromdate)
					{
						$events[] = $thisevent;
					}
				}
				else
				{
					$events[] = $thisevent;
				}
			}
		}
		else
		{
			$zzVAR1 = explode(':', $icsevent);
			if (substr($zzVAR1[0], 0, 7) == 'DTSTART')
			{
				// DTSTART:20071104
				// DTSTART:20071104T020000
				// DTSTART;TZID=Europe/Amsterdam:20170327T160000
				$zzVAR1 = explode('Z', end($zzVAR1));
				$zzVAR1 = str_replace('T', '', $zzVAR1[0]);
				$thisevent['dtstart'] = strtotime($zzVAR1 . (strlen($zzVAR1) == 8 ? '000000' : ''));
			}
			elseif (substr($zzVAR1[0], 0, 5) == 'DTEND')
			{
				// DTEND:20071104T020000
				// DTEND;TZID=Europe/Amsterdam:20170327T160000
				$zzVAR1 = explode('Z', end($zzVAR1));
				$zzVAR1 = str_replace('T', '', $zzVAR1[0]);
				$thisevent['dtend'] = strtotime($zzVAR1 . (strlen($zzVAR1) == 8 ? '232559' : ''));
			}
			elseif ($zzVAR1[0] == 'RRULE')
			{
				$thisevent['rrule'] = end($zzVAR1);
			}
			elseif ($zzVAR1[0] == 'UID')
			{
				$thisevent['uid'] = end($zzVAR1);
			}
			elseif (substr($zzVAR1[0], 0, 13) == 'RECURRENCE-ID')
			{
				$zzVAR1 = explode('Z', end($zzVAR1));
				$zzVAR1 = str_replace('T', '', $zzVAR1[0]);
				$thisevent['recurrence-id'] = strtotime($zzVAR1 . (strlen($zzVAR1) == 8 ? '000000' : ''));
			}
		}
	}
}

// Remove events from rrule-dates that have been detached.
foreach ($events as $e1key => $e1value)
{
	if (!empty($e1value['recurrence-id']))
	{
		$rid = $e1value['recurrence-id'];
		$uid = $e1value['uid'];

		// Look for an event with the same startdate and uid, and no recurrence-id
		foreach ($events as $e2key => $e2value)
		{
			if ($e1key != $e2key)
			{
				if ($e2value['uid'] == $uid)
				{
					if (!isset($e2value['recurrence-id']))
					{
						if ($e2value['dtstart'] == $rid)
						{
							unset($events[$e2key]);
						}
					}
				}
			}
		}
	}
}
usort($events, function ($a, $b)
{
	return $a['dtstart'] <=> $b['dtstart'];
});
print_r($events);

?>
