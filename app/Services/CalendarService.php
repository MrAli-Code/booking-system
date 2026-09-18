<?php
namespace BBS\App\Services;

class CalendarService
{
    private array $monthDays = [0, 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    private array $monthNames = [
        '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];
    private array $dayNames = [
        'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه',
    ];

    public function toJalali(int $year, int $month, int $day): array
    {
        $base = $this->gregorianToJalali($year, $month, $day);
        return $base;
    }

    public function toGregorian(int $jYear, int $jMonth, int $jDay): array
    {
        return $this->jalaliToGregorian($jYear, $jMonth, $jDay);
    }

    public function getDayOfWeek(string $date): int
    {
        $ts = strtotime($date);
        return (int) date('w', $ts);
    }

    public function getDayName(string $date): string
    {
        $dow = $this->getDayOfWeek($date);
        return $this->dayNames[$dow] ?? '';
    }

    public function getJalaliDate(string $date = null): array
    {
        $ts = $date ? strtotime($date) : time();
        return $this->gregorianToJalali(
            (int) date('Y', $ts),
            (int) date('m', $ts),
            (int) date('d', $ts)
        );
    }

    public function formatJalali(string $date = null, string $format = 'Y/m/d'): string
    {
        $j = $this->getJalaliDate($date);
        $result = $format;
        $result = str_replace('Y', (string) $j[0], $result);
        $result = str_replace('m', sprintf('%02d', $j[1]), $result);
        $result = str_replace('d', sprintf('%02d', $j[2]), $result);
        return $result;
    }

    public function getJalaliMonthName(int $month): string
    {
        return $this->monthNames[$month] ?? '';
    }

    public function isJalaliLeapYear(int $year): bool
    {
        $rem = $year % 33;
        return in_array($rem, [1, 5, 9, 13, 17, 22, 26, 30]);
    }

    public function getJalaliMonthDays(int $year, int $month): int
    {
        if ($month === 12 && !$this->isJalaliLeapYear($year)) {
            return 29;
        }
        return $this->monthDays[$month] ?? 30;
    }

    public function getMonthRange(string $jalaliYearMonth): array
    {
        sscanf($jalaliYearMonth, '%d/%d', $year, $month);
        $start = $this->jalaliToGregorian($year, $month, 1);
        $days = $this->getJalaliMonthDays($year, $month);
        $end = $this->jalaliToGregorian($year, $month, $days);
        return [
            'start' => sprintf('%04d-%02d-%02d', $start[0], $start[1], $start[2]),
            'end' => sprintf('%04d-%02d-%02d', $end[0], $end[1], $end[2]),
            'year' => $year,
            'month' => $month,
            'month_name' => $this->monthNames[$month],
            'days' => $days,
        ];
    }

    private function gregorianToJalali(int $gY, int $gM, int $gD): array
    {
        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $jDaysInMonth = [0, 31, 62, 93, 124, 155, 186, 216, 246, 276, 306, 336];

        $gy = $gY - 1600;
        $gm = $gM - 1;
        $gd = $gD - 1;

        $gDayNo = $gy * 365 + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);
        for ($i = 0; $i < $gm; ++$i) {
            $gDayNo += $gDaysInMonth[$i + 1] - $gDaysInMonth[$i];
        }
        if ($gm > 1 && (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0))) {
            $gDayNo++;
        }
        $gDayNo += $gd;

        $jDayNo = $gDayNo - 79;
        $jNp = (int)($jDayNo / 12053);
        $jDayNo %= 12053;
        $jy = 979 + 33 * $jNp + 4 * (int)($jDayNo / 1461);
        $jDayNo %= 1461;

        if ($jDayNo >= 366) {
            $jy += (int)(($jDayNo - 1) / 365);
            $jDayNo = ($jDayNo - 1) % 365;
        }

        for ($i = 0; $i < 11 && $jDayNo >= $jDaysInMonth[$i + 1] - $jDaysInMonth[$i]; ++$i) {
            $jDayNo -= $jDaysInMonth[$i + 1] - $jDaysInMonth[$i];
        }

        $jm = $i + 1;
        $jd = $jDayNo + 1;

        return [$jy, $jm, $jd];
    }

    private function jalaliToGregorian(int $jY, int $jM, int $jD): array
    {
        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $jDaysInMonth = [0, 31, 62, 93, 124, 155, 186, 216, 246, 276, 306, 336];

        $jy = $jY - 979;
        $jm = $jM - 1;
        $jd = $jD - 1;

        $jDayNo = 365 * $jy + (int)($jy / 33) * 8 + (int)(($jy % 33 + 3) / 4);
        for ($i = 0; $i < $jm; ++$i) {
            $jDayNo += $jDaysInMonth[$i + 1] - $jDaysInMonth[$i];
        }
        $jDayNo += $jd;

        $gDayNo = $jDayNo + 79;

        $gy = 1600 + 400 * (int)($gDayNo / 146097);
        $gDayNo %= 146097;

        $leap = true;
        if ($gDayNo >= 36525) {
            $gDayNo--;
            $gy += 100 * (int)($gDayNo / 36524);
            $gDayNo %= 36524;

            if ($gDayNo >= 365) {
                $gDayNo++;
            } else {
                $leap = false;
            }
        }

        $gy += 4 * (int)($gDayNo / 1461);
        $gDayNo %= 1461;

        if ($gDayNo >= 366) {
            $leap = false;
            $gDayNo--;
            $gy += (int)($gDayNo / 365);
            $gDayNo %= 365;
        }

        for ($i = 0; $i < 11 && $gDayNo >= $gDaysInMonth[$i + 1] - $gDaysInMonth[$i]; ++$i) {
            $gDayNo -= $gDaysInMonth[$i + 1] - $gDaysInMonth[$i];
        }

        $gm = $i + 1;
        $gd = $gDayNo + 1;

        return [$gy, $gm, $gd];
    }
}
