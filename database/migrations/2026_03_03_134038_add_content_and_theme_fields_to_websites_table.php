    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        /**
         * Run the migrations.
         */
        public function up(): void
        {
            Schema::table('websites', function (Blueprint $table) {

                // TEXT CONTENTS (make nullable so existing rows don't break)
                $table->string('aboutme_headline')->nullable();
                $table->string('player_tagline')->nullable();
                $table->text('player_bio')->nullable(); // <- bio should be TEXT, not string

                $table->string('highlights_headline')->nullable();
                $table->string('highlights_tagline')->nullable();

                $table->string('schedules_headline')->nullable();
                $table->string('schedules_tagline')->nullable();

                $table->string('acad_accolades_headline')->nullable();
                $table->string('acad_accolades_tagline')->nullable();
                $table->text('academic_accolades')->nullable();

                $table->string('sport_accolades_headline')->nullable();
                $table->string('sport_accolades_tagline')->nullable();
                $table->text('sports_accolades')->nullable();

                // Colors (also nullable, or provide defaults)
                $table->string('primary_color', 7)->nullable();
                $table->string('secondary_color', 7)->nullable();
                $table->string('accent_color', 7)->nullable();
                $table->string('background_color', 7)->nullable();
                $table->string('surface_color', 7)->nullable();
                $table->string('text_primary_color', 7)->nullable();
                $table->string('text_secondary_color', 7)->nullable();

                // EMBEDS (use TEXT because embed codes / URLs can exceed 255)
                $table->text('contact_form_embed')->nullable();
                $table->text('yt_embed')->nullable();
                $table->text('yt_playlist_embed')->nullable();

                // Footer logos: if it's multiple logos, store JSON
                $table->json('logos')->nullable();
            });
        }

        public function down(): void
        {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn([
                    'aboutme_headline',
                    'player_tagline',
                    'player_bio',
                    'highlights_headline',
                    'highlights_tagline',
                    'schedules_headline',
                    'schedules_tagline',
                    'acad_accolades_headline',
                    'acad_accolades_tagline',
                    'academic_accolades',
                    'sport_accolades_headline',
                    'sport_accolades_tagline',
                    'sports_accolades',
                    'primary_color',
                    'secondary_color',
                    'accent_color',
                    'background_color',
                    'surface_color',
                    'text_primary_color',
                    'text_secondary_color',
                    'contact_form_embed',
                    'logos',
                ]);
            });
        }
    };
