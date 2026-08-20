<?php

namespace Tests\Feature;

use Tests\TestCase;

class UserViewStackTest extends TestCase
{
    public function test_legacy_user_blade_views_are_removed(): void
    {
        $this->assertFalse(view()->exists('user.lookup'));
        $this->assertFalse(view()->exists('user.layout'));
        $this->assertFalse(view()->exists('user.login'));
        $this->assertFalse(view()->exists('user.vocab'));
        $this->assertFalse(view()->exists('user.vocab-show'));
        $this->assertFalse(view()->exists('user.media'));
        $this->assertFalse(view()->exists('user.media-show'));
        $this->assertFalse(view()->exists('user.profile'));
        $this->assertFalse(view()->exists('user.listening'));
        $this->assertFalse(view()->exists('user.quiz.hub'));
        $this->assertFalse(view()->exists('user.quiz.play'));
        $this->assertFalse(view()->exists('user.puzzle.index'));
        $this->assertFalse(view()->exists('welcome'));

        $this->assertTrue(view()->exists('app'));
        $this->assertTrue(view()->exists('partials.theme-init'));
        $this->assertTrue(view()->exists('admin.layout'));
        $this->assertTrue(view()->exists('admin.login'));
        $this->assertTrue(view()->exists('admin.dashboard'));
    }
}
