<?php

namespace BeeDelivery\BeeMaps\Enums;

enum Service: string
{
    case Autocomplete = 'autocomplete';
    case Geocoding = 'geocoding';
    case PlaceSearch = 'place_search';
    case Routing = 'routing';
    case RouteMatrix = 'route_matrix';
    case RouteOptimization = 'route_optimization';
}
