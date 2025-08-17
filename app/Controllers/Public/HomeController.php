<?php namespace Controllers\Public;

use Controllers\Controller;
use Models\Account\Services\TokenService;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class HomeController extends Controller
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("welcome", [
            'prices' => $this->getPrices(),
        ]);
    }

    #[Get("/health")]
    public function health(): Response
    {
        return $this->json(['status' => 'ok']);
    }

    private function getPrices(): array
    {
        $priceService = new TokenService();
        $eth = $priceService->getEth();
        $booe = $priceService->getBooe();
        return [
            'eth' => $eth->usdPrice,
            'booe' => $booe->usdPrice,
        ];
    }
}
