<?php

use Illuminate\Support\Facades\Schedule;



Schedule::command('notifications:due-soon')->hourly();
