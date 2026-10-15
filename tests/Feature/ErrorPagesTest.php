<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_page_shows_the_branded_not_found_page(): void
    {
        $this->get('/dashboard/no-such-page')
            ->assertNotFound()
            ->assertSee('الصفحة غير موجودة')
            ->assertSee('assets/dashboard/img/lockup.png', false);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function errors(): array
    {
        return [
            '401' => [401, 'يلزم تسجيل الدخول'],
            '403' => [403, 'لا تملك صلاحية الوصول'],
            '419' => [419, 'انتهت صلاحية الصفحة'],
            '429' => [429, 'طلبات كثيرة'],
            '500' => [500, 'حدث خطأ غير متوقّع'],
            '503' => [503, 'اللوحة قيد الصيانة'],
        ];
    }

    #[DataProvider('errors')]
    public function test_each_error_has_its_own_page(int $status, string $title): void
    {
        Route::get('/error-page-test', fn () => abort($status));

        $this->get('/error-page-test')
            ->assertStatus($status)
            ->assertSee($title)
            ->assertSee('class="error-code"', false);
    }

    public function test_api_errors_stay_json(): void
    {
        $this->getJson('/dashboard/api/v1/no-such-endpoint')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
