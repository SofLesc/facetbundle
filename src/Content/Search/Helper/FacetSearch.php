<?php
/**
 * Created by PhpStorm.
 * User: jlchassaing
 * Date: 27/01/2018
 * Time: 08:04
 */

namespace Gie\FacetBundle\Content\Search\Helper;

use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Search\SearchResult;
use Gie\FacetBuilder\Pagination\Pagerfanta\ContentSearchAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class FacetSearch
{
    const FACET_QUERYSTRING_IDENTIFIER = "facet";

    /**
     * @var FacetSearchHelper[]
     */
    private $searchHelpers;

    /** @var array */
    private $facetFilters;

    /**
     * @var string
     */
    private $facetFilterString;

    /**
     * @var array
     */
    private $defaultFacets;

    private $searchService;


    /**
     * @var FacetLoader
     */
    private $facetLoader;

    public function __construct(FacetLoader $facetLoader,  SearchService $searchService)
    {
        $this->facetLoader = $facetLoader;
        $this->searchService = $searchService;
    }

    /**
     * @param $facetsSettings
     * @param Request $request
     * @return FacetSearch
     */
    public function init($facetsSettings, Request $request = null)
    {
        if ($facetsSettings)
        return $this->registerFacetHelpers($facetsSettings)
                    ->extractFacetFilterString($request);
    }

    /**
     * @param FacetConfig[] $facetConfigs
     *
     * @return $this
     *
     * format for params :
     * [facet_alias => [facet_Params]]
     */
    public function registerFacetHelpers( $facetConfigs )
    {
        $this->searchHelpers = [];
        foreach ( $facetConfigs as $facetConfig )
        {
            if ($facetConfig instanceof FacetConfig)
            {
                $facetHelper = $this->facetLoader->getFacetHelper($facetConfig->getAlias());
                if ($facetHelper instanceof FacetSearchHelperInterface)
                {
                    $this->searchHelpers[] = $facetHelper->setQueryFacetBuilderParams($facetConfig->getTitle(), $facetConfig->getParams());
                }
            }
            else throw new \InvalidArgumentException("FacetConfigs must be an array of ". FacetConfig::class);


        }

        return $this;
    }

    /**
     * @param string $facetFilterString
     * extract facet setting  passed threw the query string
     *
     * format facet queryString :
     *
     * facet=nom_facet:valeur;nom_facet:valeur2;nom_face2:valeur3
     * @return array
     */
    public function buildFacetFilter($facetFilterString = "")
    {
        $seachFilterRegex = '/(\w+):([^;]+)/i';
        $facetFilters = [];

        if (preg_match_all($seachFilterRegex,$facetFilterString,$filters))
        {
            foreach ($filters[1] as $key=>$filterKey)
            {
                $facetFilters[$filterKey][] = $filters[2][$key];
            }
        }
        return $facetFilters;
    }


    /**
     * extract the facet querystring value
     * @param Request $request
     *
     * @return FacetSearch
     */
    public function extractFacetFilterString(Request $request = null)
    {
        $facetFilters = $this->buildFacetFilter($this->getFacetFilterStringFromRequest($request));
        return $this->appendHandlerFacetFilters($facetFilters);

    }

    /**
     * @param $facetFilters
     * @return $this
     */
    private function appendHandlerFacetFilters(array $facetFilters = [])
    {
        if (count($facetFilters > 0))
        {
            $this->facetFilters = $facetFilters;
            foreach ( $this->searchHelpers as $helper )
            {
                if (isset($facetFilters[$helper->getFacetIdentifier()]))
                {
                    $helper->setSelectedFacetEntries($facetFilters[$helper->getFacetIdentifier()]);
                }
                else{
                    $helper->resetSelectedFacetEnties();
                }
            }
        }
        return $this;
    }

    /**
     * extract the facet querystring value
     * @param Request $request
     *
     * @return mixed|string
     */
    protected function getFacetFilterStringFromRequest(Request $request = null)
    {
        if ($this->facetFilterString === null && $request !== null)
        {
            $this->facetFilterString =  $request->get(self::FACET_QUERYSTRING_IDENTIFIER, '');
        }
        return $this->facetFilterString;
    }

    /**
     * does the queryString have a facet query
     * @return bool
     */
    protected function hasFacetQuery()
    {
        return  count($this->facetFilters) > 0 ;
    }

    /**
     * return facet filters
     * @return array
     */
    public function getFacetFilters()
    {
        if ($this->facetFilters === null)
        {
            $this->facetFilters = $this->buildFacetFilter();
        }

        return $this->facetFilters;
    }

    /**
     *
     * add a new facetfilter
     * @param $type
     * @param $value
     */
    public function addFacetFilter($type, $value)
    {
        $this->facetFilters[$type][] = $value;

        return $this->appendHandlerFacetFilters($this->facetFilters);
    }


    public function setFacetFilter($facetFilters)
    {
        $this->facetFilters = $facetFilters;
        return $this->appendHandlerFacetFilters($this->facetFilters);
    }



    /**
     * add facetFilters to query
     *
     * @param Query $query
     * @param Request $request
     *
     * @return Query
     */
    public function addQueryFilter(Query $query)
    {
        $filters = [];

        if ($this->hasFacetQuery())
        {
            foreach ( $this->searchHelpers as $helper )
            {
                if ($helper->hasFacetFilter())
                {
                    $filters[] = $helper->getFacetFilter();

                }
            }
        }

        if (count($filters))
        {
            $query->filter = new Query\Criterion\LogicalAnd($filters);
        }

        return $query;


    }

    /**
     * add Query facetBuilter for each facet
     * @param Query $query
     *
     * @return Query
     */
    public function addQueryFacets(Query $query)
    {
        foreach ( $this->searchHelpers as $helper )
        {
            $helper->addQueryFacetBuilder($query);
        }

        $facetQuery = clone $query;
        $facetQuery->limit = 0;
        if ($facetQuery instanceof LocationQuery)
        {
            $this->defaultFacets = $this->searchService->findLocations($facetQuery)->facets;
        }
        else{
            $this->defaultFacets = $this->searchService->findContentInfo($facetQuery)->facets;
        }

        return $this->addQueryFilter($query);
    }

    /**
     * @param Pagerfanta $pager
     *
     * @return ContentSearchAdapter
     */
    public function getContentSearchAdapter(Pagerfanta $pager)
    {
        return $pager->getAdapter();
    }

    /**
     * Get facets array from the pagerfanta object
     * @param Pagerfanta $pager
     *
     * @return array
     */
    public function getFacetsFromPager(Pagerfanta $pager)
    {
        $pager->getNbResults();

        return $this->buildFacets($this->getContentSearchAdapter($pager)->getFacets());
    }

    /**
     * get facets array fomr the searchContent result
     * @param SearchResult $queryResult
     * @return array
     */
    public function getFacetsFromQuery(SearchResult $queryResult)
    {
        return $this->buildFacets($queryResult->facets);
    }

    /**
     * @param array $facetsToDisplayAfterFilter
     * @return array
     */
    private function buildFacets(array $facetsToDisplayAfterFilter)
    {
        $facets = [];

        foreach ( $this->defaultFacets as $id=> $facet )
        {
            foreach ( $this->searchHelpers as $helper )
            {

                if ( $helper->canVisit( $facet ) )
                {
                    $facets[ $helper->getName() ] = $helper->formatFacet( $facet, $this->facetFilterString, $facetsToDisplayAfterFilter[$id] );
                }
            }
        }

        return $facets;
    }

}