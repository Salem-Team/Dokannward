<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'name' => 'Sarah Johnson',
                'title' => 'Marketing Director',
                'company' => 'TechCorp Inc.',
                'content' => 'The quality of these bags is exceptional! I\'ve been using my leather backpack for over a year now, and it still looks brand new. The attention to detail and craftsmanship is truly outstanding.',
                'avatar' => 'https://ui-avatars.com/api/?name=Sarah+Johnson&background=3b82f6&color=fff&size=200',
                'rating' => 5,
                'is_featured' => true,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'Michael Chen',
                'title' => 'Software Engineer',
                'company' => 'StartupHub',
                'content' => 'Perfect for my daily commute! The laptop compartment is well-padded and fits my 15-inch laptop perfectly. The design is sleek and professional - I get compliments on it all the time.',
                'avatar' => 'https://ui-avatars.com/api/?name=Michael+Chen&background=10b981&color=fff&size=200',
                'rating' => 5,
                'is_featured' => true,
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'Emma Williams',
                'title' => 'Freelance Designer',
                'company' => null,
                'content' => 'I absolutely love my new handbag! It\'s spacious enough for all my essentials but doesn\'t look bulky. The leather is so soft and the color is exactly what I wanted. Highly recommend!',
                'avatar' => 'https://ui-avatars.com/api/?name=Emma+Williams&background=f59e0b&color=fff&size=200',
                'rating' => 5,
                'is_featured' => true,
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'David Rodriguez',
                'title' => 'Travel Blogger',
                'company' => 'WanderLust Travel',
                'content' => 'As someone who travels frequently, I need a reliable bag. This travel bag has been with me through 15 countries and still going strong. Water-resistant, durable, and stylish!',
                'avatar' => 'https://ui-avatars.com/api/?name=David+Rodriguez&background=8b5cf6&color=fff&size=200',
                'rating' => 5,
                'is_featured' => false,
                'is_active' => true,
                'display_order' => 4,
            ],
            [
                'name' => 'Lisa Anderson',
                'title' => 'Business Consultant',
                'company' => 'Anderson & Associates',
                'content' => 'Professional, elegant, and functional. This bag has everything I need for business meetings. Multiple compartments keep me organized, and the quality speaks for itself.',
                'avatar' => 'https://ui-avatars.com/api/?name=Lisa+Anderson&background=ef4444&color=fff&size=200',
                'rating' => 5,
                'is_featured' => false,
                'is_active' => true,
                'display_order' => 5,
            ],
            [
                'name' => 'James Wilson',
                'title' => 'University Student',
                'company' => null,
                'content' => 'Great value for money! The backpack is perfect for carrying my books and laptop to campus. It\'s comfortable to wear even when fully loaded. Would definitely buy again.',
                'avatar' => 'https://ui-avatars.com/api/?name=James+Wilson&background=06b6d4&color=fff&size=200',
                'rating' => 4,
                'is_featured' => false,
                'is_active' => true,
                'display_order' => 6,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::create($testimonial);
        }
    }
}
