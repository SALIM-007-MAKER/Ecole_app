<?php
declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use App\Shared\Api\FilterService;

class FilterTest extends TestCase
{
    private function service(): FilterService
    {
        return new FilterService();
    }

    private function parse(array $get, array $whitelist, string $alias = ''): array
    {
        $_GET = $get;
        return $this->service()->parse($whitelist, $alias);
    }

    public function test_no_filters_returns_empty(): void
    {
        $_GET  = [];
        $result = $this->service()->parse(['nom', 'statut'], 'e');
        $this->assertEmpty($result['conditions']);
        $this->assertEmpty($result['bindings']);
    }

    public function test_simple_eq_filter(): void
    {
        $result = $this->parse(['filter' => ['statut' => 'actif']], ['statut'], 'e');
        $this->assertCount(1, $result['conditions']);
        $this->assertStringContainsString('= ?', $result['conditions'][0]);
        $this->assertEquals(['actif'], $result['bindings']);
    }

    public function test_like_operator(): void
    {
        $result = $this->parse(['filter' => ['nom' => ['op' => 'like', 'value' => 'Diop']]], ['nom'], 'e');
        $this->assertStringContainsString('LIKE', $result['conditions'][0]);
        $this->assertEquals(['%Diop%'], $result['bindings']);
    }

    public function test_gt_operator(): void
    {
        $result = $this->parse(['filter' => ['age' => ['op' => 'gt', 'value' => '18']]], ['age'], '');
        $this->assertStringContainsString('>', $result['conditions'][0]);
    }

    public function test_in_operator(): void
    {
        $result = $this->parse(['filter' => ['statut' => ['op' => 'in', 'value' => 'actif,inactif']]], ['statut'], '');
        $this->assertStringContainsString('IN', $result['conditions'][0]);
        $this->assertCount(2, $result['bindings']);
    }

    public function test_null_operator(): void
    {
        $result = $this->parse(['filter' => ['deleted_at' => ['op' => 'null']]], ['deleted_at'], '');
        $this->assertStringContainsString('IS NULL', $result['conditions'][0]);
        $this->assertEmpty($result['bindings']);
    }

    public function test_not_null_operator(): void
    {
        $result = $this->parse(['filter' => ['deleted_at' => ['op' => 'not_null']]], ['deleted_at'], '');
        $this->assertStringContainsString('IS NOT NULL', $result['conditions'][0]);
    }

    public function test_unknown_field_ignored(): void
    {
        $result = $this->parse(['filter' => ['injection' => '1 OR 1=1']], ['nom'], 'e');
        $this->assertEmpty($result['conditions']);
    }

    public function test_unknown_operator_ignored(): void
    {
        $result = $this->parse(['filter' => ['nom' => ['op' => 'INVALID', 'value' => 'test']]], ['nom'], '');
        $this->assertEmpty($result['conditions']);
    }

    public function test_alias_prefix(): void
    {
        $result = $this->parse(['filter' => ['statut' => 'actif']], ['statut'], 'e');
        $this->assertStringContainsString('`e`.`statut`', $result['conditions'][0]);
    }

    public function test_starts_with_operator(): void
    {
        $result = $this->parse(['filter' => ['nom' => ['op' => 'starts', 'value' => 'Ali']]], ['nom'], '');
        $this->assertEquals(['Ali%'], $result['bindings']);
    }
}
