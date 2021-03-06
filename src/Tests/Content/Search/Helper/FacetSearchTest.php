<?php
/**
 * Created by PhpStorm.
 * User: jlchassaing
 * Date: 11/05/18
 * Time: 11:29
 */


namespace Gie\FacetBundle\Tests\Content\Search\Helper;

use eZ\Publish\API\Repository\SearchService;
use Gie\FacetBundle\Content\Search\Helper\FacetConfig;
use Gie\FacetBundle\Content\Search\Helper\FacetLoader;
use Gie\FacetBundle\Content\Search\Helper\FacetSearch;
use PHPUnit\Framework\TestCase;

class FacetSearchTest extends TestCase
{

    /**
     * @var FacetSearch
     */
    public $facetSearch;

    public function setUp()/* The :void return type declaration that should be here would cause a BC issue */
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->facetSearch = $this->getFacetSearchForTests();
    }

    /**
     * @return FacetSearch
     * @throws \ReflectionException
     */
    private function getFacetSearchForTests()
    {

        $facetLoader = $this->createMock(FacetLoader::class);
        $searchService = $this->createMock(SearchService::class);

        return new FacetSearch($facetLoader,$searchService);

    }


    public function testGetFacetFilters()
    {

        $this->assertInternalType('array' , $this->facetSearch->getFacetFilters());

    }

    /**
     * @return array
     */
    public function facetBuilderProvider()
    {
        return [
            ["", []],
            ['test:truc', ['test' => ['truc']]],
            ['test:truc;test2:truc2', ['test' => ['truc'], 'test2' => ['truc2']]],
            ['test:truc;test2:truc2;test:truc3', ['test' => ['truc','truc3'], 'test2' => ['truc2']]],
        ];
    }

    /**
     * @dataProvider facetBuilderProvider
     */
    public function testBuildFacetFilter($query, $expected)
    {
        $this->assertEquals($expected, $this->facetSearch->buildFacetFilter($query));

    }


    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRegisterFacetHelpersException()
    {
        $this->facetSearch->registerFacetHelpers(['test']);

    }

    public function testRegisterFacetHelpers()
    {
        $facetConfig = new FacetConfig("test", "title", []);

        $this->assertInstanceOf(FacetSearch::class,$this->facetSearch->registerFacetHelpers([$facetConfig]));
    }

}