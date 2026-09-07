<?php

declare(strict_types=1);

namespace Tests\Feature\Sites;

use App\Importers\CsvSiteParser;
use App\Importers\ImporterException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsvSiteParserTest extends TestCase
{
    #[Test]
    public function it_normalises_rows_and_matches_columns_in_any_order(): void
    {
        $csv = <<<'CSV'
        Name,Website,Client,CMS
        Alpha,https://alpha.test,Alpha Ltd,craft
        Beta,beta.test,,WordPress
        CSV;

        $parsed = CsvSiteParser::parse($csv);

        $this->assertSame(0, $parsed['skipped']);
        $this->assertCount(2, $parsed['sites']);

        [$alpha, $beta] = $parsed['sites'];

        $this->assertSame('Alpha', $alpha->name);
        $this->assertSame('https://alpha.test', $alpha->url);
        $this->assertSame('craft', $alpha->cmsType);
        $this->assertSame('Alpha Ltd', $alpha->suggestedClient);

        // Bare host gets an https scheme; blank client and unknown-cased CMS handled.
        $this->assertSame('https://beta.test', $beta->url);
        $this->assertSame('wordpress', $beta->cmsType);
        $this->assertNull($beta->suggestedClient);
    }

    #[Test]
    public function it_defaults_name_to_host_and_leaves_unknown_cms_unset(): void
    {
        $parsed = CsvSiteParser::parse("url\nhttps://gamma.test/path");

        $this->assertCount(1, $parsed['sites']);
        $this->assertSame('gamma.test', $parsed['sites'][0]->name);
        $this->assertSame('', $parsed['sites'][0]->cmsType);
    }

    #[Test]
    public function it_skips_rows_without_a_usable_url(): void
    {
        $csv = "url,name\nhttps://ok.test,OK\n,No URL\nnot a url,Bad\n";

        $parsed = CsvSiteParser::parse($csv);

        $this->assertCount(1, $parsed['sites']);
        $this->assertSame(2, $parsed['skipped']);
        $this->assertSame('https://ok.test', $parsed['sites'][0]->url);
    }

    #[Test]
    public function it_handles_a_semicolon_delimiter_and_a_bom(): void
    {
        $csv = "\xEF\xBB\xBFurl;name\nhttps://delta.test;Delta";

        $parsed = CsvSiteParser::parse($csv);

        $this->assertCount(1, $parsed['sites']);
        $this->assertSame('Delta', $parsed['sites'][0]->name);
        $this->assertSame('https://delta.test', $parsed['sites'][0]->url);
    }

    #[Test]
    public function it_rejects_a_file_without_a_url_column(): void
    {
        $this->expectException(ImporterException::class);

        CsvSiteParser::parse("name,client\nAlpha,Alpha Ltd");
    }

    #[Test]
    public function it_rejects_an_empty_file(): void
    {
        $this->expectException(ImporterException::class);

        CsvSiteParser::parse("   \n");
    }
}
