<?php

namespace BeeDelivery\BeeMaps\Enums;

enum TravelMode: string
{
    case Drive = 'drive';
    case TwoWheeler = 'two_wheeler';
    case Bicycle = 'bicycle';
    case Walk = 'walk';
}
