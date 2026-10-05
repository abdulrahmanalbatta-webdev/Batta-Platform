<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\SiteBlock;
use App\Models\User;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_gets_the_original_texts_until_something_is_edited(): void
    {
        $defaults = SiteContent::defaults();

        $this->getJson(route('site.content'))
            ->assertOk()
            ->assertJsonPath('data.announcement', $defaults['announcement'])
            ->assertJsonPath('data.services.0.id', 'websites')
            ->assertJsonPath('data.case_studies.0.kpis.0', ['value' => '-62%', 'label' => 'مواعيد ضائعة'])
            ->assertJsonPath('data.photo', null);
    }

    public function test_an_editor_changes_a_section_and_the_site_sees_it_at_once(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $this->getJson(route('site.content')); // cached

        $this->actingAs($editor)->putJson(route('api.site-content.update', 'announcement'), ['value' => [
            'enabled' => true,
            'text' => 'دورة Laravel الجديدة متاحة الآن',
            'link' => '/courses',
            'link_label' => 'سجّل',
            'stray' => '<script>',
        ]])->assertOk()->assertJsonMissingPath('data.stray');

        $this->getJson(route('site.content'))
            ->assertJsonPath('data.announcement.text', 'دورة Laravel الجديدة متاحة الآن')
            ->assertJsonMissingPath('data.announcement.stray');
        $this->assertSame('محتوى الموقع: شريط الإعلان', Activity::query()->latest('id')->first()->subject_name);
    }

    public function test_lists_with_nested_items_are_validated_and_kept_clean(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $study = ['id' => 'clinic', 'title' => 'نظام عيادة', 'tag' => 'تطبيق', 'sector' => 'صحة', 'problem' => 'حجوزات ضائعة', 'solution' => 'حجز أونلاين', 'tech' => ['Laravel'], 'kpis' => [['value' => '×2', 'label' => 'الحجوزات', 'x' => 1]]];

        $this->actingAs($editor)->putJson(route('api.site-content.update', 'case_studies'), ['value' => [$study]])
            ->assertOk()
            ->assertJsonPath('data.0.kpis.0', ['value' => '×2', 'label' => 'الحجوزات']);

        $invalid = $this->actingAs($editor)->putJson(route('api.site-content.update', 'case_studies'), ['value' => [[...$study, 'id' => 'Not Valid', 'kpis' => [['value' => '', 'label' => 'x']]]]])
            ->assertJsonValidationErrors(['value.0.id', 'value.0.kpis.0.value']);
        $this->assertSame('حقل الرقم مطلوب.', $invalid->json('errors')['value.0.kpis.0.value'][0]);

        $this->actingAs($editor)->putJson(route('api.site-content.update', 'highlights'), ['value' => [['icon' => 'rocket', 'title' => 'أ', 'text' => 'ب']]])
            ->assertJsonValidationErrors('value.0.icon');
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'socials'), ['value' => [['network' => 'github', 'url' => 'javascript:alert(1)']]])
            ->assertJsonValidationErrors('value.0.url');
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'technologies'), ['value' => ['laravel', 'cobol']])
            ->assertJsonValidationErrors('value.1');
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'faqs'), ['value' => array_fill(0, 21, ['q' => 'س', 'a' => 'ج'])])
            ->assertJsonValidationErrors('value');
    }

    public function test_a_section_goes_back_to_the_original_text(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'reasons'), ['value' => ['سبب واحد']])->assertOk();

        $this->actingAs($editor)->deleteJson(route('api.site-content.destroy', 'reasons'))
            ->assertOk()
            ->assertJsonPath('data', SiteContent::defaults()['reasons']);
    }

    public function test_only_content_editors_change_it_and_unknown_sections_are_404(): void
    {
        $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.site-content.show'))
            ->assertOk()
            ->assertJsonPath('meta.groups.home', 'الرئيسية')
            ->assertJsonPath('meta.definitions.services.item.icon.type', 'icon');
        $this->actingAs(User::factory()->role(Role::Support)->create())->putJson(route('api.site-content.update', 'reasons'), ['value' => []])->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Editor)->create())->putJson(route('api.site-content.update', 'nope'), ['value' => []])->assertNotFound();
    }

    public function test_every_original_text_passes_its_own_schema(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();

        foreach (SiteContent::defaults() as $key => $value) {
            $saved = $this->actingAs($editor)->putJson(route('api.site-content.update', $key), ['value' => $value])->assertOk()->json('data');
            $this->assertEquals($value, $saved, $key);
        }
    }

    public function test_page_texts_are_nested_groups_validated_and_kept_clean(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $texts = SiteContent::defaults()['texts_general'];
        $texts['auth']['perks'][0]['stray'] = 'x';
        $texts['login']['title'] = 'مرحباً من جديد';

        $this->actingAs($editor)->putJson(route('api.site-content.update', 'texts_general'), ['value' => $texts])
            ->assertOk()
            ->assertJsonMissingPath('data.auth.perks.0.stray');
        $this->getJson(route('site.content'))->assertJsonPath('data.texts_general.login.title', 'مرحباً من جديد');

        $texts['login']['title'] = '';
        $texts['auth']['perks'][0]['icon'] = 'rocket';
        $invalid = $this->actingAs($editor)->putJson(route('api.site-content.update', 'texts_general'), ['value' => $texts])
            ->assertJsonValidationErrors(['value.login.title', 'value.auth.perks.0.icon']);
        $this->assertSame('حقل العنوان مطلوب.', $invalid->json('errors')['value.login.title'][0]);
    }

    public function test_budget_options_keep_a_whole_dollar_amount(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $texts = SiteContent::defaults()['texts_pages'];
        $texts['contact']['budgets'] = [['label' => 'حوالي ألف', 'amount' => '1,000']];

        $this->actingAs($editor)->putJson(route('api.site-content.update', 'texts_pages'), ['value' => $texts])
            ->assertJsonValidationErrors('value.contact.budgets.0.amount');

        $texts['contact']['budgets'][0]['amount'] = '1000';
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'texts_pages'), ['value' => $texts])->assertOk();
        $this->getJson(route('site.content'))->assertJsonPath('data.texts_pages.contact.budgets.0', ['label' => 'حوالي ألف', 'amount' => '1000']);
    }

    public function test_a_section_saved_before_a_field_existed_gets_that_fields_original_text(): void
    {
        $old = SiteContent::defaults()['texts_general'];
        unset($old['footer']['made_with'], $old['maintenance']);
        $old['login']['title'] = 'عنوان محفوظ قديماً';
        SiteBlock::query()->create(['key' => 'texts_general', 'value' => $old]);

        $this->getJson(route('site.content'))
            ->assertJsonPath('data.texts_general.login.title', 'عنوان محفوظ قديماً')
            ->assertJsonPath('data.texts_general.footer.made_with', 'صُنع بـ Vue')
            ->assertJsonPath('data.texts_general.maintenance', SiteContent::defaults()['texts_general']['maintenance']);
    }

    public function test_project_images_are_uploaded_shown_as_urls_and_deleted_when_dropped(): void
    {
        Storage::fake('public');
        $editor = User::factory()->role(Role::Editor)->create();
        $upload = fn (): string => $this->actingAs($editor)->postJson(route('api.site-images.store'), ['image' => $this->png('shot.png', 400)])
            ->assertCreated()->json('data.path');
        $cover = $upload();
        $shot = $upload();

        $studies = SiteContent::defaults()['case_studies'];
        $studies[0]['cover'] = $cover;
        $studies[0]['gallery'] = [$shot];
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'case_studies'), ['value' => $studies])->assertOk();

        $this->getJson(route('site.content'))
            ->assertJsonPath('data.case_studies.0.cover', Storage::disk('public')->url($cover))
            ->assertJsonPath('data.case_studies.0.gallery', [Storage::disk('public')->url($shot)])
            ->assertJsonPath('data.case_studies.1.cover', null);
        $this->actingAs($editor)->getJson(route('api.site-content.show'))->assertJsonPath('data.case_studies.0.cover', $cover);

        $studies[0]['gallery'] = [];
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'case_studies'), ['value' => $studies])->assertOk();
        Storage::disk('public')->assertMissing($shot);
        Storage::disk('public')->assertExists($cover);

        $this->actingAs($editor)->deleteJson(route('api.site-content.destroy', 'case_studies'))->assertOk();
        Storage::disk('public')->assertMissing($cover);
    }

    public function test_images_must_be_uploaded_ones(): void
    {
        $studies = SiteContent::defaults()['case_studies'];
        $studies[0]['cover'] = '../../.env';

        $this->actingAs(User::factory()->role(Role::Editor)->create())->putJson(route('api.site-content.update', 'case_studies'), ['value' => $studies])
            ->assertJsonValidationErrors('value.0.cover');
    }

    public function test_projects_saved_before_images_existed_get_empty_ones(): void
    {
        $studies = SiteContent::defaults()['case_studies'];
        unset($studies[0]['cover'], $studies[0]['gallery'], $studies[0]['link']);
        SiteBlock::query()->create(['key' => 'case_studies', 'value' => $studies]);

        $this->getJson(route('site.content'))
            ->assertJsonPath('data.case_studies.0.cover', null)
            ->assertJsonPath('data.case_studies.0.gallery', [])
            ->assertJsonPath('data.case_studies.0.title', $studies[0]['title']);
    }

    /**
     * A real PNG of the given size, built without the GD extension (as in AvatarControllerTest).
     */
    private function png(string $name, int $size): UploadedFile
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\0".str_repeat("\x33\x66\x99", $size), $size);

        return UploadedFile::fake()->createWithContent($name, "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($rows))
            .$chunk('IEND', ''));
    }

    public function test_the_photo_is_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $editor = User::factory()->role(Role::Editor)->create();

        $first = $this->actingAs($editor)->postJson(route('api.site-photo.store'), ['photo' => $this->png('me.png', 400)])->assertOk()->json('data.photo');
        $this->assertNotNull($first);
        $this->getJson(route('site.content'))->assertJsonPath('data.photo', $first);

        $this->actingAs($editor)->postJson(route('api.site-photo.store'), ['photo' => $this->png('me2.png', 400)])->assertOk();
        $this->assertCount(1, Storage::disk('public')->files('site'));

        $this->actingAs($editor)->postJson(route('api.site-photo.store'), ['photo' => $this->png('tiny.png', 50)])->assertJsonValidationErrors('photo');

        $this->actingAs($editor)->deleteJson(route('api.site-photo.destroy'))->assertOk();
        $this->assertCount(0, Storage::disk('public')->files('site'));
        $this->getJson(route('site.content'))->assertJsonPath('data.photo', null);
    }
}
