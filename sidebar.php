
<?php

$currentPage = basename($_SERVER['PHP_SELF']);

$basePath = str_contains($_SERVER['PHP_SELF'], '/pages/')
    ? '../'
    : '';

$menuItems = [

    // OVERVIEW
    [
        'section' => 'OVERVIEW',
        'items' => [
            [
                'label' => 'Dashboard',
                'icon' => 'bi-grid-1x2',
                'file' => 'index.php',
                'path' => $basePath . 'index.php'
            ]
        ]
    ],

    // PART 1 — WEEKS 4–5
    [
        'section' => 'PART 1 · WEEKS 4–5',
        'items' => [
            [
                'label' => 'Borrowers',
                'icon' => 'bi-people',
                'file' => 'borrowers.php',
                'path' => $basePath . 'pages/borrowers.php'
            ],
            [
                'label' => 'Equipment',
                'icon' => 'bi-box-seam',
                'file' => 'equipment.php',
                'path' => $basePath . 'pages/equipment.php'
            ],
            [
                'label' => 'Transactions & Items',
                'icon' => 'bi-arrow-left-right',
                'file' => 'transactions.php',
                'path' => $basePath . 'pages/transactions.php'
            ]
        ]
    ],

    // PART 2 — WEEK 6
    [
        'section' => 'PART 2 · WEEK 6',
        'items' => [
            [
                'label' => 'Retrieve, Filter, Sort, Group',
                'icon' => 'bi-funnel',
                'file' => 'week06_queries.php',
                'path' => $basePath . 'pages/week06_queries.php'
            ]
        ]
    ],

    // PART 3 — WEEK 7
    [
        'section' => 'PART 3 · WEEK 7',
        'items' => [
            [
                'label' => 'Joins, Sets & Functions',
                'icon' => 'bi-diagram-3',
                'file' => 'week07_queries.php',
                'path' => $basePath . 'pages/week07_queries.php'
            ]
        ]
    ],

    // PART 4 — WEEK 8
    [
        'section' => 'PART 4 · WEEK 8',
        'items' => [
            [
                'label' => 'Views, CASE & Transactions',
                'icon' => 'bi-eye',
                'file' => 'week08_queries.php',
                'path' => $basePath . 'pages/week08_queries.php'
            ]
        ]
    ],

    // PART 5 — WEEK 10 (ACTIVE)
    [
        'section' => 'PART 5 · WEEK 10',
        'items' => [
            [
                'label' => 'Functions & Triggers',
                'icon' => 'bi-code-square',
                'file' => 'week10_queries.php',
                'path' => $basePath . 'pages/week10_queries.php'
            ]
        ]
    ]

];

?>

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="brand-icon">
            <i class="bi bi-database-fill"></i>
        </div>

        <div>
            <h5>DB Practical</h5>
            <small>ADMINISTRATION SYSTEM</small>
        </div>

    </div>


    <nav class="sidebar-navigation">

        <?php foreach ($menuItems as $section): ?>

            <div class="sidebar-section-label">
                <?= htmlspecialchars($section['section'], ENT_QUOTES, 'UTF-8') ?>
            </div>

            <?php foreach ($section['items'] as $item): ?>

                <?php
                    $isActive = $currentPage === $item['file'];
                    $isSoon = !empty($item['soon']);
                ?>

                <?php if ($isSoon): ?>

                    <div
                        class="sidebar-link disabled-link"
                        title="This module will be developed later"
                        aria-disabled="true"
                    >

                        <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>

                        <span>
                            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <small class="soon-label">SOON</small>

                    </div>

                <?php else: ?>

                    <a
                        href="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>"
                        class="sidebar-link <?= $isActive ? 'active' : '' ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >

                        <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>

                        <span>
                            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </a>

                <?php endif; ?>

            <?php endforeach; ?>

        <?php endforeach; ?>

    </nav>


    <div class="sidebar-footer">

        <div class="sidebar-footer-icon">
            <i class="bi bi-database-check"></i>
        </div>

        <div>
            <strong>Database System</strong>
            <small>PostgreSQL Administration</small>
        </div>

    </div>

</aside>
