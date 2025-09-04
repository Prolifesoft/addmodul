<?php
require_once __DIR__ . '/../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Ensure processing function is available
if (!function_exists('process_wordpress_queue')) {
    require_once __DIR__ . '/hooks.php';
}

if (isset($_GET['retry'])) {
    $id = (int) $_GET['retry'];
    Capsule::table('mod_wordpress_queue')
        ->where('id', $id)
        ->update([
            'status' => 'pending',
            'attempts' => 0,
            'last_attempt' => null,
            'error_message' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    process_wordpress_queue();
    header('Location: modulequeue.php');
    exit;
}

$queueItems = Capsule::table('mod_wordpress_queue')->orderBy('id')->get();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Modül İşlem Kuyruğu</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Modül İşlem Kuyruğu</h2>
    <p>Kuyrukta <?php echo count($queueItems); ?> Öğe</p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Domain</th>
                <th>Durum</th>
                <th>Denenme</th>
                <th>Son Hata</th>
                <th>Son Deneme</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($queueItems) === 0): ?>
            <tr><td colspan="7">Kuyrukta öğe yok</td></tr>
        <?php else: foreach ($queueItems as $item): ?>
            <tr>
                <td><?php echo $item->id; ?></td>
                <td><?php echo htmlspecialchars($item->domain); ?></td>
                <td><?php echo htmlspecialchars($item->status); ?></td>
                <td><?php echo $item->attempts; ?></td>
                <td><?php echo htmlspecialchars($item->error_message); ?></td>
                <td><?php echo $item->last_attempt ? date('Y-m-d H:i:s', $item->last_attempt) : '-'; ?></td>
                <td><a href="?retry=<?php echo $item->id; ?>">Yeniden Dene</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</body>
</html>
