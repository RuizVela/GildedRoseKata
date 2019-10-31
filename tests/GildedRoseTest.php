<?php

use App\GildedRose;
use PHPUnit\Framework\TestCase;

class GildedRoseTest extends TestCase
{
    public function testSellInCalidadRestan()
    {
        $name = 'Culo';
        $quality = 2;
        $sellIn = 2;

        $valorSellinResta = 1;
        $valorQualityResta = 1;

        $gildedRose = new GildedRose($name, $quality, $sellIn);

        $gildedRose->tick();
        
        $responseSellIn = $gildedRose->sellIn;
        $responseQuality = $gildedRose->quality;

        $expectedSellIn = $sellIn - $valorSellinResta;
        $expectedQuality = $quality - $valorQualityResta;

        $this->assertEquals($expectedQuality, $responseQuality);
        $this->assertEquals($expectedSellIn, $responseSellIn);
    }
    
    public function testSiCalidadBajaMasSiCaduca()
    {
        $name = 'Culo';
        $quality = 2;
        $sellIn = 0;
        $valorQualityResta = 2;

        $gildedRose = new GildedRose($name, $quality, $sellIn);
        $gildedRose->tick();

        $responseQuality = $gildedRose->quality;
        $expectedQuality = $quality - $valorQualityResta;

        $this->assertEquals($expectedQuality, $responseQuality);
    }

    public function testCompruebaCalidadMinima()
    {
        $name = 'Culo';
        $quality = 0;
        $sellIn = 0;
        $calidadMinima = 0;

        $gildedRose = new GildedRose($name, $quality, $sellIn);
        $gildedRose->tick();

        $responseQuality = $gildedRose->quality;
        $expectedQuality = $calidadMinima;

        $this->assertEquals($expectedQuality, $responseQuality);
    }
    public function testSiCalidadAgedBrieSube()
    {
        $name = 'Aged Brie';
        $quality = 1;
        $sellIn = 1;
        $valorAumentaCalidad = 1;

        $gildedRose = new GildedRose($name, $quality, $sellIn);
        $gildedRose->tick();
        $response = $gildedRose->quality;
        $expected = $quality+$valorAumentaCalidad;
        $this->assertEquals($expected, $response);
    }
    public function testCompruebaCalidadMaxima()
    {
        $name = 'Aged Brie';
        $quality = 50;
        $sellIn = 1;
        $calidadMaxima = 50;

        $gildedRose = new GildedRose($name, $quality, $sellIn);
        $gildedRose->tick();
        $response = $gildedRose->quality;
        $expected = $calidadMaxima;
        $this->assertEquals($expected, $response);
    }
    public function testSellInNoRestaSiSulfuras()
    {
        $name = 'Sulfuras, Hand of Ragnaros';
        $quality = 1;
        $sellIn = 1;

        $gildedRose = new GildedRose($name, $quality, $sellIn);

        $gildedRose->tick();
        $responseSellIn = $gildedRose->sellIn;
        $responseQuality = $gildedRose->quality;

        $this->assertEquals($quality, $responseQuality);
        $this->assertEquals($sellIn, $responseSellIn);
    }
    public function testSiCalidadTicketSubeFase1()
    {
        $name = 'Backstage passes to a TAFKAL80ETC concert';
        $quality = 1;
        $valorAumentaCalidad = 1;
        $sellIn = 11;

        $gildedRose = new GildedRose($name, $quality, $sellIn);

        $gildedRose->tick();
        $responseQuality = $gildedRose->quality;
        $expectedQuality = $quality+$valorAumentaCalidad;

        $this->assertEquals($expectedQuality, $responseQuality);
    }
    public function testSiCalidadTicketSubeFase2()
    {
        $name = 'Backstage passes to a TAFKAL80ETC concert';
        $quality = 1;
        $valorAumentaCalidad = 2;
        $sellIn = 10;

        $gildedRose = new GildedRose($name, $quality, $sellIn);

        $gildedRose->tick();
        $responseQuality = $gildedRose->quality;
        $expectedQuality = $quality+$valorAumentaCalidad;

        $this->assertEquals($expectedQuality, $responseQuality);
    }
    public function testSiCalidadTicketSubeFase3()
    {
        $name = 'Backstage passes to a TAFKAL80ETC concert';
        $quality = 1;
        $valorAumentaCalidad = 3;
        $sellIn = 5;

        $gildedRose = new GildedRose($name, $quality, $sellIn);

        $gildedRose->tick();
        $responseQuality = $gildedRose->quality;
        $expectedQuality = $quality+$valorAumentaCalidad;

        $this->assertEquals($expectedQuality, $responseQuality);
    }
    public function testSiTicketCaducado()
    {
        $name = 'Backstage passes to a TAFKAL80ETC concert';
        $quality = 1;
        $valorTicketCaducado = 0;
        $sellIn = 0;

        $gildedRose = new GildedRose($name, $quality, $sellIn);

        $gildedRose->tick();
        $responseQuality = $gildedRose->quality;

        $this->assertEquals($valorTicketCaducado, $responseQuality);
    }
}

