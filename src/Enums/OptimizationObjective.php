<?php

namespace BeeDelivery\BeeMaps\Enums;

enum OptimizationObjective: string
{
    case MinTravelTime = 'min_travel_time';
    case MinDistance = 'min_distance';
}
