<?php

namespace Tests\Feature\Api;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogCommentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_visitor_comment_always_starts_pending_even_when_status_is_sent(): void
    {
        $post = BlogPost::factory()->published()->create();

        $this->postJson("/api/blog/posts/{$post->slug}/comments", [
            'author_name' => 'João da Silva', 'body' => "Ótimo artigo!\nAjudou bastante.", 'status' => 'approved', 'moderated_at' => now(),
        ])->assertCreated()->assertJsonPath('message', 'Seu comentário foi enviado e aguarda aprovação.');

        $this->assertDatabaseHas('blog_comments', ['blog_post_id' => $post->id, 'author_name' => 'João da Silva', 'status' => 'pending', 'moderated_at' => null]);
        $this->getJson("/api/blog/posts/{$post->slug}/comments")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_approved_comments_are_public(): void
    {
        $post = BlogPost::factory()->published()->create();
        $approved = BlogComment::factory()->approved()->create(['blog_post_id' => $post->id]);
        foreach (['pending', 'rejected', 'hidden'] as $status) {
            BlogComment::factory()->create(['blog_post_id' => $post->id, 'status' => $status]);
        }

        $this->getJson("/api/blog/posts/{$post->slug}/comments")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id)
            ->assertJsonMissingPath('data.0.status');
    }

    public function test_comments_require_a_published_post(): void
    {
        $draft = BlogPost::factory()->create();

        $this->postJson("/api/blog/posts/{$draft->slug}/comments", ['author_name' => 'Ana', 'body' => 'Olá, tudo bem?'])->assertNotFound();
        $this->getJson("/api/blog/posts/{$draft->slug}/comments")->assertNotFound();
        $this->assertDatabaseCount('blog_comments', 0);
    }

    public function test_comment_content_is_validated_as_plain_text(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $post = BlogPost::factory()->published()->create();
        $url = "/api/blog/posts/{$post->slug}/comments";

        $this->postJson($url, [])->assertJsonValidationErrors(['author_name', 'body']);
        $this->postJson($url, ['author_name' => 'A', 'body' => 'ok'])->assertJsonValidationErrors(['author_name', 'body']);
        $this->postJson($url, ['author_name' => '<b>Ana</b>', 'body' => 'Texto válido'])->assertJsonValidationErrors('author_name');
        $this->postJson($url, ['author_name' => 'Ana', 'body' => 'Veja <script>alert(1)</script>'])->assertJsonValidationErrors('body');
        $this->postJson($url, ['author_name' => 'Ana', 'body' => str_repeat('a', 2001)])->assertJsonValidationErrors('body');
        $this->postJson($url, ['author_name' => 'Ana', 'body' => 'https://a.example https://b.example https://c.example'])->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('blog_comments', 0);
    }

    public function test_honeypot_duplicates_and_rate_limit_block_spam(): void
    {
        $post = BlogPost::factory()->published()->create();
        $url = "/api/blog/posts/{$post->slug}/comments";

        $this->postJson($url, ['author_name' => 'Bot', 'body' => 'Compre agora', 'website' => 'https://spam.example'])->assertCreated();
        $this->assertDatabaseCount('blog_comments', 0);

        $this->postJson($url, ['author_name' => 'Ana', 'body' => 'Mesma mensagem'])->assertCreated();
        $this->postJson($url, ['author_name' => 'Ana', 'body' => 'Mesma mensagem'])->assertJsonValidationErrors('body');

        $this->postJson($url, ['author_name' => 'Ana', 'body' => 'Mensagem 3'])->assertCreated();
        $this->postJson($url, ['author_name' => 'Ana', 'body' => 'Mensagem 4'])->assertCreated();
        $this->postJson($url, ['author_name' => 'Ana', 'body' => 'Mensagem 5'])->assertTooManyRequests();
    }

    public function test_moderation_is_restricted_to_administrators(): void
    {
        $comment = BlogComment::factory()->create();

        $this->getJson('/api/admin/blog/comments')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
        $this->getJson('/api/admin/blog/comments')->assertForbidden();
        $this->patchJson("/api/admin/blog/comments/{$comment->id}", ['status' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $comment->fresh()->status);
    }

    public function test_administrator_filters_and_moderates_comments(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
        $post = BlogPost::factory()->published()->create();
        $comment = BlogComment::factory()->create(['blog_post_id' => $post->id]);
        BlogComment::factory()->approved()->create(['blog_post_id' => $post->id]);
        BlogComment::factory()->create();

        $this->getJson("/api/admin/blog/comments?blog_post_id={$post->id}&status=pending")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.post.id', $post->id)
            ->assertJsonPath('counts.pending', 1)->assertJsonPath('counts.approved', 1);

        $this->patchJson("/api/admin/blog/comments/{$comment->id}", ['status' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->getJson("/api/blog/posts/{$post->slug}/comments")->assertJsonCount(2, 'data');

        $this->patchJson("/api/admin/blog/comments/{$comment->id}", ['status' => 'hidden'])->assertOk();
        $this->getJson("/api/blog/posts/{$post->slug}/comments")->assertJsonCount(1, 'data');

        $this->patchJson("/api/admin/blog/comments/{$comment->id}", ['status' => 'rejected'])->assertOk();
        $this->patchJson("/api/admin/blog/comments/{$comment->id}", ['status' => 'pending'])->assertJsonValidationErrors('status');
    }
}
