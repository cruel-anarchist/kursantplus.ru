# PROJECT_CHAT_CONTEXT

## Что это за проект

- Проект: сайт автошколы `Курсант +`
- Стек: `Astro 6`, статическая сборка
- Основной язык шаблонов: `Astro / HTML`
- Стили: один общий файл `src/styles/global.css`
- Данные и контент: вынесены в `src/data/*.ts`
- Деплой: `GitHub Pages` через `GitHub Actions`
- Backend формы: отдельный `PHP` endpoint на `api.kursantplus.ru`

## Ключевая идея архитектуры

- Страницы и секции собираются из простых `Astro`-компонентов.
- Большая часть текстов, тарифов, карточек и списков лежит в `src/data`.
- Основная страница сильно data-driven: меняем данные в `src/data`, а не хардкодим текст в разметке без необходимости.
- Проект не построен вокруг React/Vue/Svelte. Здесь обычный `Astro` + немного `inline JS`.

## Текущая структура проекта

```text
src/
  assets/
    social-source/             исходники соц-иконок

  components/
    Header.astro               шапка
    TopContactStrip.astro      верхняя полоса контактов/соцсетей
    Footer.astro               футер
    SectionHeading.astro       универсальный заголовок секции
    ProgramCard.astro          карточка программы
    PricingCard.astro          карточка тарифа
    IconBadge.astro            маленькие икон-блоки
    LegalSectionTable.astro    таблицы для страницы сведений
    icons/                     локальные Astro-иконки

  data/
    site.ts                    мета, контакты, навигация, соцсети
    programs.ts                программы, тарифы, этапы, форматы, преимущества
    services.ts                допуслуги, финансы, автопарк, документы
    legal.ts                   данные для страницы "Сведения"

  layouts/
    BaseLayout.astro           общий layout, SEO, schema.org, глобальный JS

  pages/
    index.astro                главная страница
    info.astro                 сведения об образовательной организации
    404.astro                  404
    robots.txt.ts
    sitemap.xml.ts
    icon-style-preview.astro   служебная страница предпросмотра

  styles/
    global.css                 вся визуальная система проекта

public/
  images/
    brand/                     логотипы
    hero/                      hero-графика
    cars/                      изображения автомобилей
    materials/                 доп. материалы
    graphics/
    patterns/
    social/
  icons/                       SVG-иконки
  documents/                   документы для публикации
```

## Главные точки входа

- Главная страница: [F:\web_курсант_плюс\src\pages\index.astro](F:\web_курсант_плюс\src\pages\index.astro)
- Общий layout: [F:\web_курсант_плюс\src\layouts\BaseLayout.astro](F:\web_курсант_плюс\src\layouts\BaseLayout.astro)
- Глобальные стили: [F:\web_курсант_плюс\src\styles\global.css](F:\web_курсант_плюс\src\styles\global.css)

## Где менять что

### Контент

- Контакты, телефон, e-mail, соцсети, hero-текст, навигация:
  [F:\web_курсант_плюс\src\data\site.ts](F:\web_курсант_плюс\src\data\site.ts)
- Программы, тарифы, преимущества, форматы, этапы:
  [F:\web_курсант_плюс\src\data\programs.ts](F:\web_курсант_плюс\src\data\programs.ts)
- Допуслуги, автопарк, финансы, документы:
  [F:\web_курсант_плюс\src\data\services.ts](F:\web_курсант_плюс\src\data\services.ts)
- Юридические/официальные сведения:
  [F:\web_курсант_плюс\src\data\legal.ts](F:\web_курсант_плюс\src\data\legal.ts)

### Разметка секций

- Главная и состав секций:
  [F:\web_курсант_плюс\src\pages\index.astro](F:\web_курсант_плюс\src\pages\index.astro)
- Хедер:
  [F:\web_курсант_плюс\src\components\Header.astro](F:\web_курсант_плюс\src\components\Header.astro)
- Верхняя контактная полоса:
  [F:\web_курсант_плюс\src\components\TopContactStrip.astro](F:\web_курсант_плюс\src\components\TopContactStrip.astro)
- Футер:
  [F:\web_курсант_плюс\src\components\Footer.astro](F:\web_курсант_плюс\src\components\Footer.astro)

### Стили

- Почти всё оформляется в одном файле:
  [F:\web_курсант_плюс\src\styles\global.css](F:\web_курсант_плюс\src\styles\global.css)

## Правила по HTML / Astro

- Использовать семантическую разметку: `section`, `article`, `aside`, `nav`, `header`, `footer`, `dl`, `ul`.
- Новые секции по возможности собирать через существующие компоненты, а не создавать лишние одноразовые обёртки.
- Если текст повторно используется или может часто меняться, выносить его в `src/data`, а не держать в шаблоне.
- В проекте принят подход с плоскими секциями и понятными блоками: не усложнять разметку без необходимости.
- Для условных классов использовать `class:list`.
- Для повторяющихся списков использовать `.map(...)` из data-файлов.

## Правила по JS

- Основной JS в проекте без фреймворков.
- Локальные интерактивные вещи допускаются через `<script is:inline>`.
- Глобальная интерактивность уже живёт в `BaseLayout.astro`:
  - версия для слабовидящих
  - мобильное меню
  - speech/announce логика
  - parallax-логика
- Если логика относится только к одному компоненту, лучше держать её рядом с этим компонентом, как в `TopContactStrip.astro`.
- Не добавлять тяжёлые библиотеки ради простых эффектов.
- При добавлении JS стараться не ломать доступность и не дублировать уже существующие обработчики.

## Правила по CSS

- В проекте один большой файл `global.css`.
- Нейминг в основном BEM-подобный:
  - `block`
  - `block__element`
  - `block--modifier`
- Цвета, радиусы, тени, контейнер и transition лежат в `:root`.
- Сначала искать существующий паттерн, потом добавлять новый.
- Не плодить почти одинаковые стили, если можно расширить существующий модификатором.
- В проекте уже есть устоявшиеся паттерны:
  - `section--band`
  - `section--dark`
  - `section--interactive-feature`
  - `timeline-reveal`
  - карточки `program-card`, `pricing-card`, `service-card`, `finance-card`, `fleet-card`
- Для адаптива ориентир:
  - desktop базовый
  - `max-width: 1059px` планшетный слой
  - `max-width: 760px` мобильный слой
  - иногда есть `max-width: 640px` для более плотной мобильной настройки

## Правила по контенту и UX

- Тон проекта: официальный, спокойный, продающий, без перегруза.
- Не писать внутренние/черновые формулировки в духе “этот блок лучше показать тут”.
- Если можно выбрать между “слишком рекламно” и “спокойно, но убедительно”, обычно выбирать второй вариант.
- На главной уже много интерактивных раскрывающихся блоков; новые похожие секции лучше делать в том же ритме.
- Для карточек и промо следить за аккуратным вертикальным выравниванием CTA и цен.

## Текущие важные особенности проекта

### 1. Временная линейка графики `-two`

Сейчас сайт временно переключён на альтернативный набор графики `-two`.

Активные временные ассеты:

- логотип: `/images/brand/logo-emblem-two-256.webp`
- hero: `/images/hero/hero-driving-school-main-two.webp`
- машины: `/images/cars/*-two.webp`

Старый временный вариант логотипа сохранён отдельно как:

- `/images/brand/logo-emblem-one-256.webp`

Если нужно вернуть оригинальную линейку, проверять и менять ссылки здесь:

- [F:\web_курсант_плюс\src\components\Header.astro](F:\web_курсант_плюс\src\components\Header.astro)
- [F:\web_курсант_плюс\src\components\Footer.astro](F:\web_курсант_плюс\src\components\Footer.astro)
- [F:\web_курсант_плюс\src\layouts\BaseLayout.astro](F:\web_курсант_плюс\src\layouts\BaseLayout.astro)
- [F:\web_курсант_плюс\src\styles\global.css](F:\web_курсант_плюс\src\styles\global.css)
- [F:\web_курсант_плюс\src\data\services.ts](F:\web_курсант_плюс\src\data\services.ts)

### 2. `dist/` не является исходником

- `dist/` — это build output.
- Рабочие ассеты должны лежать в `public/`.
- Если пользователь временно складывает новые изображения в `dist/`, их нужно:
  1. проверить,
  2. подготовить для web,
  3. перенести/сконвертировать в `public/`,
  4. только потом подключать в сайт.

### 3. Доступность

- В проекте уже есть отдельный режим для слабовидящих.
- При изменении цветов, фонов, размеров и интерактивных блоков важно помнить про селекторы `body[data-vision-mode="accessible"] ...`
- Если добавляется новый нестандартный UI-блок, для него часто нужно хотя бы базово продумать accessible-слой.

## Работа с графикой

- Предпочтительный production-формат для сайта: `webp`
- Если приходит `png/jpg`:
  - проверить размер
  - проверить прозрачность, если она нужна
  - конвертировать в `webp`
  - оригинал можно оставить рядом, если он нужен как исходник
- Для логотипов с прозрачностью важно сохранять alpha-канал
- Для hero-графики ориентир: не раздувать вес без необходимости

## Команды разработки

```bash
npm run dev
npm run build
npm run preview
```

## Правила коммита

- Перед коммитом желательно проверить:

```bash
npm run build
```

- Коммиты делать короткими и по смыслу:
  - `Update temporary hero artwork`
  - `Switch site to temporary two asset set`
  - `Polish promo banner and contact interactions`

## GitHub Pages: как здесь устроен деплой

Деплой настроен через workflow:

- [F:\web_курсант_плюс\.github\workflows\deploy-pages.yml](F:\web_курсант_плюс\.github\workflows\deploy-pages.yml)

Что важно:

- Pages деплоится автоматически при `push` в ветку `main`
- GitHub Actions сам:
  1. делает `npm ci`
  2. запускает `npm run build`
  3. публикует `./dist`

То есть вручную `dist/` в git пушить не нужно.

## Минимальный сценарий коммит + пуш на GitHub Pages

```bash
git status
git add .
git commit -m "Короткое понятное описание"
git push origin main
```

После пуша в `main` GitHub Pages обновится через workflow автоматически.

## Backend формы

- Публичный endpoint формы: `https://api.kursantplus.ru/form-handler.php`
- Корневая директория backend на хостинге: `www/api.kursantplus.ru/`
- Статический frontend остаётся на `GitHub Pages`, а форма отправляется на отдельный `PHP` backend.
- Backend отвечает за:
  - валидацию формы
  - сохранение заявок в `MySQL`
  - отправку email-уведомления
- Исходники backend для выкладки лежат в:
  [F:\web_курсант_плюс\deploy\api.kursantplus.ru](F:\web_курсант_плюс\deploy\api.kursantplus.ru)

### Важно по секретам

- Реальный `config.php` для backend не должен попадать в git.
- Все пароли и доступы хранить только локально, вне репозитория.
- В репозитории можно держать только:
  - `config.sample.php`
  - безопасные описания инфраструктуры
  - инструкции без секретов

## Практическое правило для новых чатов

Если в новом чате нужно быстро понять проект, сначала смотреть в таком порядке:

1. [F:\web_курсант_плюс\PROJECT_CHAT_CONTEXT.md](F:\web_курсант_плюс\PROJECT_CHAT_CONTEXT.md)
2. [F:\web_курсант_плюс\src\pages\index.astro](F:\web_курсант_плюс\src\pages\index.astro)
3. [F:\web_курсант_плюс\src\data\site.ts](F:\web_курсант_плюс\src\data\site.ts)
4. [F:\web_курсант_плюс\src\data\programs.ts](F:\web_курсант_плюс\src\data\programs.ts)
5. [F:\web_курсант_плюс\src\data\services.ts](F:\web_курсант_плюс\src\data\services.ts)
6. [F:\web_курсант_плюс\src\styles\global.css](F:\web_курсант_плюс\src\styles\global.css)

Этого обычно достаточно, чтобы продолжить работу без долгого повторного погружения.
