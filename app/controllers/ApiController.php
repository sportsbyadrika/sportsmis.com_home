<?php
namespace Controllers;

use Core\Controller;
use Models\ApiModel;

class ApiController extends Controller
{
    public function states(string $country_id): void
    {
        $this->json(ApiModel::getStates((int)$country_id));
    }

    public function districts(string $state_id): void
    {
        $this->json(ApiModel::getDistricts((int)$state_id));
    }
}
