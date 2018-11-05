<div class="container">
    <h1>Index</h1>

    <div>
        <form method="post">
            <?= $form ?>
            <input type="submit"/>
        </form>
    </div>

    <p><?= $message ?></p>
    <p>Title: <?= $dummy->getTitle() ?></p>
    <p>Src: <?= $image->getSrc() ?></p>
</div>