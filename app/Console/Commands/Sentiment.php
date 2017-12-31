<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use App\Util;

/**
 * Class ExampleCommand
 * @package Bowhead\Console\Commands
 */
class Sentiment extends Command
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
    protected $description = 'Stocktwits Sentiments bot';

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
        $st          = new Util\Stocktwits(null, null);

        $this->wc = $wc;
        register_shutdown_function(array($this, 'shutdown'));

        // Enter a loop where we check the strategy every minute.
        // $fastEMA = env('EMA_FAST', 9);
        // $slowEMA = env('EMA_SLOW', 30);
        $interval = env('EMA_INTVL', 900);    // 15 minutes

        $prices = [];
        $position = false;
        $positionID = 0;

        while(1) {
            $res = $st->getStream('home');
            $messages = $res->messages;
            $bearish = 0;
            $bullish = 0;
            $total = 0;

            foreach($messages as $msg){
                if($msg->entities->sentiment != null){
                    if($msg->entities->sentiment->basic == 'Bullish')   $bullish += 1;
                    else if($msg->entities->sentiment->basic == 'Bearish')  $bearish += 1;
                    $total += 1;
                }
            }

            if($bullish === 0 || $bearish === 0)  return;
            $bullishPer = (($bullish / $total) * 100);

            if($bullishPer > 60){   // bullish sentiment
                if($position != 'Bullish'){
                    $this->wc->positionClose($positionID);
                    error_log("Going long");
                    $order = [
                        'size' => 0.05,
                        'direction' => 'long',
                        'leverage' => 1
                    ];
                    $res = $this->wc->positionNew($order);
                    if(!empty($res['id']))  $positionID = $res['id'];
                    $position = 'Bullish';
                } else {
                    error_log('already long');
                }
            }

            else if($bullishPer < 40){  // bearish sentiment
                if($position != 'Bearish'){
                    $this->wc->positionClose($positionID);
                    error_log("Going short");
                    $order = [
                        'size' => 0.05,
                        'direction' => 'short',
                        'leverage' => 1
                    ];
                    $res = $this->wc->positionNew($order);
                    if(!empty($res['id']))  $positionID = $res['id'];
                    $position = 'Bearish';
                } else {
                    error_log('already short');
                }
            }

            else {
                error_log('Neutral sentiment');
            }

            sleep($interval);
        }
    }
}
