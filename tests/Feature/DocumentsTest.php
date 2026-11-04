<?php

namespace Tests\Feature;

use App\Filament\Manage\Resources\Documents\DocumentResource;
use App\Filament\Manage\Resources\Documents\Pages\CreateDocument;
use App\Filament\Manage\Resources\Documents\Pages\EditDocument;
use App\Filament\Manage\Resources\Documents\Pages\ListDocuments;
use App\Filament\Teams\Resources\Documents\DocumentResource as TeamsDocumentResource;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    public function test_guests_are_redirected_from_documents(): void
    {
        $team = Team::factory()->create();

        $this->get(TeamsDocumentResource::getUrl('index', panel: 'teams', tenant: $team))
            ->assertRedirect('/teams/login');
    }

    public function test_managers_can_create_a_pdf_document_for_their_team(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Risk assessment',
                'notes' => 'Keep this with the kit.',
                'file_path' => UploadedFile::fake()->create('risk-assessment.pdf', 120, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $document = Document::query()->where('title', 'Risk assessment')->first();

        $this->assertNotNull($document);
        $this->assertSame($team->getKey(), $document->team_id);
        $this->assertSame($manager->getKey(), $document->uploader_id);
        $this->assertSame('risk-assessment.pdf', $document->file_name);
        $this->assertSame('Keep this with the kit.', $document->notes);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertNotNull($document->file_size);
    }

    public function test_choosing_a_file_fills_sanitised_filename_and_title(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'file_path' => UploadedFile::fake()->create('Risk (2024).pdf', 120, 'application/pdf'),
            ])
            ->assertSchemaStateSet([
                'file_name' => 'Risk 2024.pdf',
                'title' => 'Risk (2024)',
            ]);
    }

    public function test_managers_can_edit_the_download_filename(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $document = Document::factory()->forTeam($team)->create([
            'file_name' => 'document.pdf',
        ]);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->fillForm([
                'file_name' => 'wall procedure.pdf',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('wall procedure.pdf', $document->fresh()->file_name);

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->fillForm([
                'file_name' => 'bad/name.pdf',
            ])
            ->call('save')
            ->assertHasFormErrors(['file_name']);
    }

    public function test_managers_can_edit_notes_and_replacing_the_file_removes_the_old_one(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $document = Document::factory()->forTeam($team)->create([
            'uploader_id' => $manager->getKey(),
            'notes' => 'Original notes',
        ]);
        $oldPath = $document->file_path;

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->fillForm([
                'title' => $document->title,
                'notes' => 'Updated notes',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated notes', $document->fresh()->notes);
        $this->assertSame($oldPath, $document->fresh()->file_path);

        $newPath = 'documents/'.$team->getKey().'/replacement.pdf';
        Storage::disk('local')->put($newPath, '%PDF-1.4 replacement');

        $document->update([
            'file_path' => $newPath,
            'file_name' => 'updated.pdf',
        ]);

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertSame('updated.pdf', $document->fresh()->file_name);
    }

    public function test_managers_can_soft_delete_restore_and_force_delete_a_document(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $document = Document::factory()->forTeam($team)->create();
        $path = $document->file_path;

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($document);
        Storage::disk('local')->assertExists($path);

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted($document);

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->callAction(DeleteAction::class)
            ->callAction(ForceDeleteAction::class);

        $this->assertDatabaseMissing('documents', ['id' => $document->getKey()]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_manage_documents_are_sorted_by_title_and_can_be_sorted_by_date(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $zebra = Document::factory()->forTeam($team)->create([
            'title' => 'Zebra',
            'updated_at' => now()->subDays(2),
        ]);
        $alpha = Document::factory()->forTeam($team)->create([
            'title' => 'Alpha',
            'updated_at' => now()->subDay(),
        ]);
        $middle = Document::factory()->forTeam($team)->create([
            'title' => 'Middle',
            'updated_at' => now(),
        ]);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$alpha, $middle, $zebra], inOrder: true)
            ->sortTable('title', 'desc')
            ->assertCanSeeTableRecords([$zebra, $middle, $alpha], inOrder: true)
            ->sortTable('updated_at', 'desc')
            ->assertCanSeeTableRecords([$middle, $alpha, $zebra], inOrder: true);
    }

    public function test_manage_documents_table_shows_the_uploader_and_hides_trashed_rows_by_default(): void
    {
        $manager = User::factory()->teamManager()->create(['name' => 'Zelda Uploader']);
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);
        Filament::setTenant($team);

        $documents = Document::factory()->forTeam($team)->count(3)->create([
            'uploader_id' => $manager->getKey(),
        ]);
        $trashed = Document::factory()->forTeam($team)->trashed()->count(2)->create([
            'uploader_id' => $manager->getKey(),
        ]);

        Livewire::test(ListDocuments::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($documents)
            ->assertCanNotSeeTableRecords($trashed)
            ->assertSeeText('Zelda Uploader')
            ->assertCanRenderTableColumn('title')
            ->assertCanRenderTableColumn('uploader.name')
            ->assertCanRenderTableColumn('updated_at');
    }

    public function test_a_system_admin_can_view_manage_documents_but_cannot_create_unless_they_manage_the_team(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $admin = User::factory()->systemAdmin()->create();
        $document = Document::factory()->forTeam($team)->create();

        $this->actingAs($admin);
        Filament::setTenant($team);

        Livewire::test(ListDocuments::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$document]);

        $this->get(DocumentResource::getUrl('create'))
            ->assertForbidden();

        Livewire::test(CreateDocument::class)
            ->assertForbidden();
    }

    public function test_pdf_validation_rejects_non_pdf_and_oversize_files(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Notes',
                'file_path' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->call('create')
            ->assertHasFormErrors(['file_path']);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Huge',
                'file_path' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasFormErrors(['file_path']);
    }

    public function test_managers_do_not_see_another_teams_documents_on_manage(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $otherTeam = Team::factory()->create();
        $own = Document::factory()->forTeam($team)->create();
        $other = Document::factory()->forTeam($otherTeam)->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);

        $this->get(DocumentResource::getUrl('edit', ['record' => $other]))
            ->assertNotFound();
    }
}
