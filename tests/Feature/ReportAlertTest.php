<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\StockNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAlertTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username): User
    {
        return User::factory()->create([
            'username' => $username,
            'role' => $role,
        ]);
    }

    private function draft(User $manager): Report
    {
        return Report::create([
            'report_no' => 'RPT-TEST-1',
            'manager_id' => $manager->id,
            'title' => 'Production Report',
            'content' => 'Daily harvest summary.',
            'report_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);
    }

    public function test_sending_a_report_notifies_owner_not_manager(): void
    {
        $owner = $this->user('owner', 'owner1');
        $manager = $this->user('manager', 'manager1');
        $report = $this->draft($manager);

        $this->actingAs($manager)
            ->post(route('manager.reports.send', $report))
            ->assertRedirect();

        $this->assertSame('Sent', $report->fresh()->status);
        $this->assertSame(1, StockNotification::count());
        $this->assertTrue(
            StockNotification::where('user_id', $owner->id)
                ->where('type', 'report')
                ->where('report_id', $report->id)
                ->exists()
        );
        $this->assertFalse(
            StockNotification::where('user_id', $manager->id)->exists()
        );
        $this->assertStringContainsString('Production Report', StockNotification::first()->body);
    }

    public function test_sending_the_same_report_twice_does_not_duplicate(): void
    {
        $this->user('owner', 'owner1');
        $manager = $this->user('manager', 'manager1');
        $report = $this->draft($manager);

        $this->actingAs($manager)->post(route('manager.reports.send', $report));
        $this->actingAs($manager)->post(route('manager.reports.send', $report));

        $this->assertSame(1, StockNotification::count());
    }

    public function test_owner_alert_links_to_the_report(): void
    {
        $owner = $this->user('owner', 'owner1');
        $manager = $this->user('manager', 'manager1');
        $report = $this->draft($manager);

        $this->actingAs($manager)->post(route('manager.reports.send', $report));

        $this->actingAs($owner)
            ->getJson(route('alerts.index'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonFragment(['title' => 'New report'])
            ->assertJsonFragment(['url' => '/owner/reports/'.$report->id]);
    }

    public function test_owner_remark_notifies_the_report_manager(): void
    {
        $owner = $this->user('owner', 'owner1');
        $manager = $this->user('manager', 'manager1');
        $report = $this->draft($manager);

        $this->actingAs($manager)->post(route('manager.reports.send', $report));

        $this->actingAs($owner)
            ->post(route('owner.reports.remark', $report), [
                'owner_comment' => 'Please review the dispatch figures.',
            ])
            ->assertRedirect(route('owner.reports'));

        $this->assertSame(
            'Please review the dispatch figures.',
            $report->fresh()->owner_comment
        );
        $this->assertTrue(
            StockNotification::where('user_id', $manager->id)
                ->where('type', 'report_remark')
                ->where('report_id', $report->id)
                ->where('title', 'New report remark')
                ->exists()
        );
    }

    public function test_owner_cannot_remark_on_a_draft_report(): void
    {
        $owner = $this->user('owner', 'owner1');
        $manager = $this->user('manager', 'manager1');
        $report = $this->draft($manager);

        $this->actingAs($owner)
            ->post(route('owner.reports.remark', $report), [
                'owner_comment' => 'This draft should stay closed.',
            ])
            ->assertNotFound();

        $this->assertNull($report->fresh()->owner_comment);
    }

    public function test_manager_cannot_send_or_delete_another_managers_report(): void
    {
        $owner = $this->user('manager', 'manager1');
        $other = $this->user('manager', 'manager2');
        $report = $this->draft($owner);

        $this->actingAs($other)
            ->post(route('manager.reports.send', $report))
            ->assertNotFound();

        $this->actingAs($other)
            ->delete(route('manager.reports.destroy', $report))
            ->assertNotFound();

        $this->assertSame('Draft', $report->fresh()->status);
    }
}
