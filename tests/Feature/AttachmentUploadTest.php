<?php

namespace Tests\Feature;

use App\Filament\Resources\Attachments\Pages\CreateAttachment;
use App\Filament\Resources\Attachments\Pages\EditAttachment;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permissions:sync');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user->fresh();
    }

    private function uploader(): User
    {
        return $this->userWith(
            'attachments.view',
            'attachments.create',
            'attachments.update',
            'attachments.delete',
        );
    }

    public function test_an_image_can_be_uploaded_and_is_stored_on_disk(): void
    {
        $this->actingAs($this->uploader());

        Livewire::test(CreateAttachment::class)
            ->fillForm([
                'title' => 'Product photos',
                'description' => 'Two sample images.',
                'files' => [
                    UploadedFile::fake()->image('front.png', 400, 300),
                    UploadedFile::fake()->image('back.png', 400, 300),
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $attachment = Attachment::firstWhere('title', 'Product photos');

        $this->assertNotNull($attachment);
        $this->assertCount(2, $attachment->getMedia('files'));

        // The file must actually exist where the media record says it does.
        foreach ($attachment->getMedia('files') as $media) {
            $this->assertFileExists($media->getPath(), "missing on disk: {$media->file_name}");
            $this->assertSame('public', $media->disk);
            $this->assertGreaterThan(0, $media->size);
        }
    }

    public function test_a_pdf_can_be_uploaded(): void
    {
        $this->actingAs($this->uploader());

        Livewire::test(CreateAttachment::class)
            ->fillForm([
                'title' => 'Invoice',
                'files' => [UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Attachment::firstWhere('title', 'Invoice')->getFirstMedia('files');

        $this->assertNotNull($media);
        $this->assertSame('invoice.pdf', $media->file_name);
        $this->assertFileExists($media->getPath());
    }

    public function test_the_title_is_required(): void
    {
        $this->actingAs($this->uploader());

        Livewire::test(CreateAttachment::class)
            ->fillForm(['title' => null])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_files_survive_editing_the_record(): void
    {
        $this->actingAs($this->uploader());

        $attachment = Attachment::create(['title' => 'Before']);
        $attachment->addMedia(UploadedFile::fake()->image('keep.png'))->toMediaCollection('files');

        Livewire::test(EditAttachment::class, ['record' => $attachment->getRouteKey()])
            ->fillForm(['title' => 'After'])
            ->call('save')
            ->assertHasNoFormErrors();

        $attachment->refresh();

        $this->assertSame('After', $attachment->title);
        $this->assertCount(1, $attachment->getMedia('files'));
    }

    public function test_deleting_the_record_removes_its_files_from_disk(): void
    {
        $this->actingAs($this->uploader());

        $attachment = Attachment::create(['title' => 'Temporary']);
        $attachment->addMedia(UploadedFile::fake()->image('gone.png'))->toMediaCollection('files');

        $path = $attachment->getFirstMedia('files')->getPath();
        $this->assertFileExists($path);

        $attachment->delete();

        $this->assertFileDoesNotExist($path);
        $this->assertDatabaseCount('media', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_the_files_page_requires_the_view_permission(): void
    {
        $this->actingAs($this->userWith('attachments.view'))
            ->get('/admin/attachments')
            ->assertOk();

        $this->actingAs($this->userWith('products.view'))
            ->get('/admin/attachments')
            ->assertForbidden();
    }

    public function test_uploading_requires_the_create_permission(): void
    {
        $this->actingAs($this->userWith('attachments.view', 'attachments.create'))
            ->get('/admin/attachments/create')
            ->assertOk();

        $this->actingAs($this->userWith('attachments.view'))
            ->get('/admin/attachments/create')
            ->assertForbidden();
    }
}
