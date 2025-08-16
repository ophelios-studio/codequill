<?php namespace Models\Core\Entities;

// TODO: Move to Zephyrus ?
class Now
{
    public string $year;
    public string $month;
    public string $day;
    public string $date_time;
    public string $date;
    public string $time;

    public function __construct()
    {
        $this->year = date('Y');
        $this->month = date('m');
        $this->day = date('d');
        $this->date_time = date(FORMAT_DATE_TIME);
        $this->date = date(FORMAT_DATE);
        $this->time = date(FORMAT_TIME);
    }
}
