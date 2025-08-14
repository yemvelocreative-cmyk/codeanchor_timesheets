function timekeeper_render($templateFile, $vars = [])
{
    $fullPath = __DIR__ . '/../templates/admin/' . $templateFile;
    if (!file_exists($fullPath)) {
        echo "<div class='alert alert-danger'>Template not found: $templateFile</div>";
        return;
    }
    extract($vars);
    include $fullPath;
}
