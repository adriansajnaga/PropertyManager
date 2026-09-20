<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create(['name' => 'Adrian']));

        $this->unit = Unit::where('description', 'Lokal 12')->firstOrFail();
    }

    public function test_a_scan_of_the_lease_can_be_attached_to_the_unit(): void
    {
        $this->post(route('units.documents.store', $this->unit), [
            'file' => UploadedFile::fake()->create('umowa-najmu.pdf', 120, 'application/pdf'),
            'title' => 'Umowa najmu',
        ])->assertRedirect();

        $document = UnitDocument::firstOrFail();

        $this->assertSame('Umowa najmu', $document->title);
        $this->assertSame('umowa-najmu.pdf', $document->original_name);
        $this->assertSame($this->unit->id, $document->unit_id);
        $this->assertSame('Adrian', $document->uploader->name);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_the_file_name_becomes_the_title_when_none_is_given(): void
    {
        $this->post(route('units.documents.store', $this->unit), [
            'file' => UploadedFile::fake()->create('protokol.jpg', 30, 'image/jpeg'),
        ])->assertRedirect();

        $this->assertSame('protokol', UnitDocument::firstOrFail()->title);
    }

    public function test_an_executable_is_refused(): void
    {
        $this->post(route('units.documents.store', $this->unit), [
            'file' => UploadedFile::fake()->create('wirus.exe', 10),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, UnitDocument::count());
    }

    public function test_the_document_can_be_downloaded_and_deleted_together_with_the_file(): void
    {
        $this->post(route('units.documents.store', $this->unit), [
            'file' => UploadedFile::fake()->create('umowa-najmu.pdf', 120, 'application/pdf'),
        ]);

        $document = UnitDocument::firstOrFail();

        $this->get(route('unit-documents.download', $document))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=umowa-najmu.pdf');

        $this->delete(route('unit-documents.destroy', $document))->assertRedirect();

        $this->assertSame(0, UnitDocument::count());
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_the_documents_are_listed_on_the_unit_page(): void
    {
        $this->post(route('units.documents.store', $this->unit), [
            'file' => UploadedFile::fake()->create('umowa-najmu.pdf', 120, 'application/pdf'),
            'title' => 'Umowa najmu',
        ]);

        $this->get(route('units.show', $this->unit))
            ->assertOk()
            ->assertSee('Umowa najmu')
            ->assertSee('umowa-najmu.pdf');
    }

    public function test_files_are_not_public(): void
    {
        $this->post(route('units.documents.store', $this->unit), [
            'file' => UploadedFile::fake()->create('umowa-najmu.pdf', 120, 'application/pdf'),
        ]);

        $document = UnitDocument::firstOrFail();

        auth()->logout();

        $this->get(route('unit-documents.download', $document))->assertRedirect(route('login'));
    }
}
