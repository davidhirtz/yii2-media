<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Traits;

use Hirtz\Media\Models\Traits\EmbedUrlTrait;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Behaviors\TranslationBehavior;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Db\I18nActiveQuery;
use Hirtz\Skeleton\Models\Interfaces\TranslationInterface;
use Hirtz\Skeleton\Models\Traits\I18nAttributesTrait;
use Hirtz\Skeleton\Models\Traits\TranslationTrait;
use Hirtz\Skeleton\Models\Translation;
use Override;
use Yii;

class EmbedUrlTraitTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->getI18n()->setLanguages(['en-US', 'de']);

        $columns = [
            'id' => 'pk',
            'embed_url' => 'string null',
        ];

        Yii::$app->getDb()->createCommand()
            ->createTable(EmbedUrlActiveRecord::tableName(), $columns)
            ->execute();
    }

    /**
     * `CREATE TABLE` commits the test transaction, so the translations are removed by hand.
     */
    #[\Override]
    protected function tearDown(): void
    {
        Translation::deleteAll(['model' => EmbedUrlActiveRecord::class]);

        Yii::$app->getDb()->createCommand()
            ->dropTable(EmbedUrlActiveRecord::tableName())
            ->execute();

        parent::tearDown();
    }

    public function testEmbedUrlAttributeLabel(): void
    {
        $model = new EmbedUrlActiveRecord();

        $this->assertEquals('Embed URL', $model->getAttributeLabel('embed_url'));
        $this->assertEquals('Embed URL (DE)', $model->getAttributeLabel('embed_url_de'));
    }

    public function testEmbedUrlValidation(): void
    {
        $model = new EmbedUrlActiveRecord();

        $this->assertEquals(['embed_url', 'embed_url_de'], $model->safeAttributes());

        $model->embed_url = 'no-url';
        $model->embed_url_de = '';

        $this->assertFalse($model->validate());

        $model->embed_url = 'https://www.test.com';

        $this->assertTrue($model->validate());
        $this->assertEquals('https://www.test.com', $model->embed_url);

        // A translated attribute is not a column, so the typecast behavior leaves the empty string alone; storage
        // treats it as no translation at all.
        $this->assertSame('', $model->embed_url_de);
    }

    public function testEmptyEmbedUrl(): void
    {
        $model = new EmbedUrlActiveRecord();
        $this->assertEquals('', $model->getFormattedEmbedUrl());
    }

    public function testYoutubeEmbedUrl(): void
    {
        $model = new EmbedUrlActiveRecord();
        $model->embed_url = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';

        $this->assertTrue($model->insert());
        $this->assertEquals('https://www.youtube.com/embed/jNQXAC9IVRw', $model->embed_url);
        $this->assertEquals('https://www.youtube.com/embed/jNQXAC9IVRw?autoplay=1&disablekb=1&modestbranding=1&rel=0', $model->getFormattedEmbedUrl());

        $model = new EmbedUrlActiveRecord();
        $model->embed_url = 'https://youtu.be/jNQXAC9IVRw?feature=youtu.be';

        $this->assertTrue($model->insert());
        $this->assertEquals('https://www.youtube.com/embed/jNQXAC9IVRw?feature=youtu.be', $model->embed_url);
    }

    public function testYoutubeLiveEmbedUrl(): void
    {
        $model = new EmbedUrlActiveRecord();
        $model->embed_url = 'https://www.youtube.com/live/jNQXAC9IVRw';

        $this->assertTrue($model->insert());
        $this->assertEquals('https://www.youtube.com/embed/jNQXAC9IVRw', $model->embed_url);
        $this->assertEquals('https://www.youtube.com/embed/jNQXAC9IVRw?autoplay=1&disablekb=1&modestbranding=1&rel=0', $model->getFormattedEmbedUrl());
    }

    public function testVimeoEmbedUrl(): void
    {
        $model = new EmbedUrlActiveRecord();
        $model->embed_url_de = 'https://vimeo.com/123456789';

        $this->assertTrue($model->insert());
        $this->assertEquals('https://player.vimeo.com/video/123456789', $model->embed_url_de);
        $this->assertEquals('https://player.vimeo.com/video/123456789?autoplay=1&dnt=1', $model->getFormattedEmbedUrl('de'));
    }
}

/**
 * @property int $id
 * @property string|null $embed_url
 * @property string|null $embed_url_de
 */
class EmbedUrlActiveRecord extends ActiveRecord implements TranslationInterface
{
    use EmbedUrlTrait;
    use I18nAttributesTrait;
    use TranslationTrait;

    #[Override]
    public function init(): void
    {
        $this->i18nAttributes = ['embed_url'];
        parent::init();
    }

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'TranslationBehavior' => TranslationBehavior::class,
        ];
    }

    public function getTranslationModelClass(): string
    {
        return self::class;
    }

    /**
     * @return I18nActiveQuery<static>
     */
    #[Override]
    public static function find(): I18nActiveQuery
    {
        return Yii::createObject(I18nActiveQuery::class, [static::class]);
    }

    #[Override]
    public function rules(): array
    {
        return $this->getTraitRules();
    }

    #[Override]
    public function attributeLabels(): array
    {
        return $this->getTraitAttributeLabels();
    }

    #[Override]
    public static function tableName(): string
    {
        return 'test_embed_url';
    }
}
