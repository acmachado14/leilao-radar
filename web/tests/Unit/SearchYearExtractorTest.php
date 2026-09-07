<?php

namespace Tests\Unit;

use App\Support\SearchYearExtractor;
use Tests\TestCase;

class SearchYearExtractorTest extends TestCase
{
    public function test_extracts_a_single_year_and_cleans_the_search(): void
    {
        $this->assertSame([
            'search' => 'Jetta GLI',
            'ano_min' => 2018,
            'ano_max' => 2018,
        ], SearchYearExtractor::extract('Jetta GLI 2018'));
    }

    public function test_extracts_a_year_range_from_search(): void
    {
        $this->assertSame([
            'search' => 'Amarok',
            'ano_min' => 2018,
            'ano_max' => 2019,
        ], SearchYearExtractor::extract('Amarok 2018/2019'));
    }

    public function test_leaves_search_untouched_when_there_is_no_year(): void
    {
        $this->assertSame([
            'search' => 'Civic 1.5 Turbo',
            'ano_min' => null,
            'ano_max' => null,
        ], SearchYearExtractor::extract('Civic 1.5 Turbo'));
    }
}
