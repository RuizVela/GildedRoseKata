<?php

namespace App;

class GildedRose
{
    public $name;
    public $quality;
    public $sellIn;
    public $valorCambioCalidad=1;
    public $calidadMaxima=50;
    public $calidadMinima=0;
    public $ticketsFase2=10;
    public $ticketsFase3=5;
    public $valorCambioSellin=1;
    public $limiteSellIn = 0;

    public function __construct($name, $quality, $sellIn)
    {
        $this->name = $name;
        $this->quality = $quality;
        $this->sellIn = $sellIn;
    }
    public function sumarCalidad()
    {
        if ($this->name != 'Aged Brie' and $this->name != 'Backstage passes to a TAFKAL80ETC concert')
        {
            return;
        }
        if ($this->quality >= $this->calidadMaxima) {
            return;
        }
        $this->quality=$this->quality+$this->valorCambioCalidad;
        return;
    }
    public function restarCalidad()
    {
        if ($this->name == 'Aged Brie' or $this->name == 'Backstage passes to a TAFKAL80ETC concert')
        {
            return;
        }
        if ($this->quality == $this->calidadMinima) {
            return;
        }
        if ($this->name == 'Sulfuras, Hand of Ragnaros') {
                    return;
        }
        $this->quality=$this->quality-$this->valorCambioCalidad;
    }
    public function restarSellIn()
    {
        if ($this->name == 'Sulfuras, Hand of Ragnaros') {
            return;
        }
        $this->sellIn=$this->sellIn-$this->valorCambioSellin;
        return;
    }
    public function calidadAlMinimo()
    {
        if ($this->name == 'Backstage passes to a TAFKAL80ETC concert') {
            $this->quality = $this->calidadMinima;
        }
        return;
    }

    public static function of($name, $quality, $sellIn) {
        return new static($name, $quality, $sellIn);
    }
    public function sumaCalidadTickets(){
        if ($this->name != 'Backstage passes to a TAFKAL80ETC concert') 
        {
            return;
        }
        if ($this->sellIn <= $this->ticketsFase2) 
        {
            $this->sumarCalidad();
        }
        if ($this->sellIn <= $this->ticketsFase3) 
        {
            $this->sumarCalidad();
        }
    }
    public function restarCalidadCaducado()
    {
        if ($this->sellIn > $this->limiteSellIn)
        {
            return;
        }
        $this->restarCalidad();
        $this->calidadAlMinimo();
    }


    public function tick()
    {
        $this->restarCalidad();
        $this->sumarCalidad();
        $this->sumaCalidadTickets();
        $this->restarSellIn();
        $this->restarCalidadCaducado();
    }
}
