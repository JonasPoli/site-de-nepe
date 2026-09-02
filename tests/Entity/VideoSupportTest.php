<?php

namespace App\Tests\Entity;

use App\Entity\VideoSupport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class VideoSupportTest extends TestCase
{
    public function testThumbnailUrlFallbackToYoutube(): void
    {
        $video = new VideoSupport();
        $video->setYoutubeId('dQw4w9WgXcQ');

        $this->assertFalse($video->hasCustomThumbnail());
        $this->assertSame('https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', $video->getThumbnailUrl());
        $this->assertSame('https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', $video->getYoutubeThumbnailUrl());
    }

    public function testThumbnailUrlWithCustomThumbnail(): void
    {
        $video = new VideoSupport();
        $video->setYoutubeId('dQw4w9WgXcQ');
        $video->setCustomThumbnail('custom_thumb_123.jpg');

        $this->assertTrue($video->hasCustomThumbnail());
        $this->assertSame('/uploads/video_thumb/custom_thumb_123.jpg', $video->getThumbnailUrl());
        $this->assertSame('https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', $video->getYoutubeThumbnailUrl());
    }

    public function testSetCustomThumbnailFileUpdatesTimestamp(): void
    {
        $video = new VideoSupport();
        $this->assertNull($video->getCustomThumbnailUpdatedAt());

        // Create dummy temp file for test
        $tempFile = tempnam(sys_get_temp_dir(), 'test_thumb');
        $file = new File($tempFile);

        $video->setCustomThumbnailFile($file);
        $this->assertNotNull($video->getCustomThumbnailUpdatedAt());
        $this->assertSame($file, $video->getCustomThumbnailFile());

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    public function testEmptyYoutubeIdReturnsEmptyFallback(): void
    {
        $video = new VideoSupport();
        $this->assertSame('', $video->getThumbnailUrl());
        $this->assertSame('', $video->getYoutubeThumbnailUrl());
    }
}
