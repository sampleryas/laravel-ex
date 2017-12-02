<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use App\Util;

/**
 * Class ExampleCommand
 * @package Bowhead\Console\Commands
 */
class WhaleClubEMACommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'app:whaleclubema';

    /**
     * @var string
     */
    protected $instrument = 'BTC-USD';

    /**
     * @var
     */
    protected $wc;

    /**
     * @var
     */
    protected $positions;

    /**
     * @var
     */
    protected $positions_time;

    /**
     * @var
     * positions attached to a indicator
     */
    protected $indicator_positions;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'EMA strategy bot';

    protected $order_cooloff;


    /**
     * @return int
     */
    public function shutdown(){
        error_log("Shutdown called");
        if (!is_array($this->indicator_positions)){
            return 0;
        }
        foreach($this->indicator_positions as $key => $val) {
            error_log("closing $key - $val");
            $this->wc->positionClose($val);
        }
        return 0;
    }

    public function ema(array $numbers, int $n){
        $m   = count($numbers);
        $α   = 2 / ($n + 1);
        $EMA = [];
        // Start off by seeding with the first data point
        $EMA[] = $numbers[0];
        // Each day after: EMAtoday = α⋅xtoday + (1-α)EMAyesterday
        for ($i = 1; $i < $m; $i++) {
            $EMA[] = ($α * $numbers[$i]) + ((1 - $α) * $EMA[$i - 1]);
        }
        return $EMA;
    }

    /**
     * @return null
     *
     *  this is the part of the command that executes.
     */
    public function handle(){
        $instruments = ['BTC/USD'];
        $wc          = new Util\Whaleclub($this->instrument);

        $this->wc = $wc;
        register_shutdown_function(array($this, 'shutdown'));

        // Enter a loop where we check the strategy every minute.
        $fastEMA = env('EMA_FAST', 9);
        $slowEMA = env('EMA_SLOW', 30);
        $interval = env('EMA_INTVL', 1800);    // 30 minutes

        $prices = [];
        $position = false;
        $positionID = 0;

        while(1) {

            $currPrice = $wc->getPrice($this->instrument)['price'];
            // $currPrice = rand(9000, 9500);
            error_log("Current price: $currPrice");
            $prices[] = $currPrice;
            if(count($prices) > $slowEMA){
                $_emaFast = $this->ema($prices, $fastEMA);
                $emaFast = $_emaFast ? array_pop($_emaFast) : 0;
                $_emaSlow = $this->ema($prices, $slowEMA);
                $emaSlow = $_emaSlow ? array_pop($_emaSlow) : 0;

                if($position === false){    // first run
                    if($emaFast > $emaSlow) $position = 'long';
                    else if($emaFast < $emaSlow) $position = 'short';
                    error_log("Position set to $position");
                }

                if($emaFast > $emaSlow){    // go long 
                    if($position == 'short'){
                        if($positionID !== 0){
                            error_log("Covering short id: $positionID");    
                            $this->wc->positionClose($positionID);
                        }
                        error_log("Going long");
                        $order = [
                            'size'=> 0.02,
                            'direction' => 'long',
                            'leverage' => 1
                        ];
                        $res = $this->wc->positionNew($order);
                        error_log('Order: '.print_r($order, true));
                        error_log('res: '.print_r($res, true));
                        if(!empty($res['id']))  $positionID = $res['id'];
                        $position = 'long';
                    } else {
                        error_log("Already long");
                    }
                }

                if($emaFast < $emaSlow){    // go short
                    if($position == 'long'){
                        if($positionID !== 0){
                            error_log("Covering long id: $positionID");    
                            $this->wc->positionClose($positionID);
                        }
                        error_log("Going short");
                        $order = [
                            'size'=> 0.02,
                            'direction' => 'short',
                            'leverage' => 1
                        ];
                        $res = $this->wc->positionNew($order);
                        error_log('Order: '.print_r($order, true));
                        error_log('res: '.print_r($res, true));
                        if(!empty($res['id']))  $positionID = $res['id'];
                        $position = 'short';
                    } else {
                        error_log("Already short");   
                    }
                }

                $arr = [$currPrice, $emaSlow, $emaFast];
                error_log(implode(', ', $arr));
            }

            if(count($prices) > 300){
                array_shift($prices);   // drop older values
            }

            sleep($interval);
        }
    }
}
