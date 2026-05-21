<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Rendering;

final class NoticeRenderer
{
    public function render(string $message, string $className = 'cpi-notice'): string
    {
        return '<p class="'.esc_attr($className).'">'.esc_html($message).'</p>';
    }
}
