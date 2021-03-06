<?php

require 'functions.php';

$errorMessage = null;
$warnings = [];
try {
    if (isset($_POST['submit'])) {
        shell_exec('rm -rf ./tmp');
        mkdir('./tmp');
        $fileToTest = './tmp/' . $_FILES['file']['name'];
        if (move_uploaded_file($_FILES['file']['tmp_name'], $fileToTest)) {
            $extractCmd = getExtractCommand($_FILES['file']);
            if (empty($extractCmd)) {
                throw new RuntimeException("The file extension is not suported.");
            }
            shell_exec("cd tmp/ && " . $extractCmd . " " . $_FILES['file']['name']);

            $mageFile = rsearch('./tmp/', '/Mage\.php/');
            if ($mageFile) {
                include $mageFile;
                $mageVersion = Mage::getVersionInfo();
                $mageEdition = Mage::getEdition();

                if ($mageEdition == Mage::EDITION_COMMUNITY) {
                    $vanillaVersionPrefix = 'CE';
                } else {
                    $vanillaVersionPrefix = 'EE';
                }
                $mageVersionString = $mageVersion['major'] . '.' . $mageVersion['minor'] . '.' . $mageVersion['revision'] . '.' . $mageVersion['patch'];
                $vanillaVersion = $vanillaVersionPrefix . '-' . $mageVersionString;
                if (!file_exists('./vanillas/' . $vanillaVersion)) {
                    throw new RuntimeException(sprintf('The version %s is not available for comparison!', $vanillaVersion));
                }
                $vanillaMagentoFolder = realpath("./vanillas/" . $vanillaVersion);

                $appliedPatchesFile = rsearch('./tmp/', '/applied\.patches\.list/');
                if (!empty($appliedPatchesFile)) {
                    $appliedPatches = getAppliedPatches($appliedPatchesFile);

                    shell_exec('rm -rf ./vanillas/' . $vanillaVersion . '-patched');
                    shell_exec('cp -R ./vanillas/' . $vanillaVersion . ' ./vanillas/' . $vanillaVersion . '-patched');

                    foreach ($appliedPatches as $appliedPatch) {
                        $patchFile = getPatchFile($appliedPatch, $vanillaVersionPrefix, $mageVersionString);
                        if ($patchFile === null) {
                            $warnings[] = sprintf('Patch %s not found!', $appliedPatch);
                        }

                        shell_exec('cp ./vanillas/patches/' . $patchFile . ' ./vanillas/' . $vanillaVersion . '-patched/');
                        shell_exec('cd ./vanillas/' . $vanillaVersion . '-patched/ && sh ' . $patchFile);
                    }

                    $vanillaMagentoFolder = './vanillas/' . $vanillaVersion . '-patched';
                }

                $mageRootFolder = realpath(dirname($mageFile) . '/../');
                $cmd = "diff -urbB " . $vanillaMagentoFolder . "  " . $mageRootFolder . " | lsdiff";
                $result = shell_exec($cmd);
                $result = str_replace($mageRootFolder, '', $result);
            }
        }
    }
} catch (RuntimeException $e) {
    $errorMessage = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Magento Project Checker</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css"
          integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1>Magento Project Checker
                <small>by Summa Solutions</small>
            </h1>
            <?php if (isset($_POST['submit'])): ?>
                <?php if (!empty($warnings)): ?>
                    <p class="bg-warning"><?php echo implode($warnings, '<br />'); ?></p>
                <?php endif; ?>
                <?php if (!empty($cmd)): ?>
                    <strong>Command executed:</strong>
                    <pre><?php echo $cmd; ?></pre><br/>
                <?php endif; ?>
                <?php if (!is_null($errorMessage)): ?>
                    <p class="bg-danger"><?php echo $errorMessage; ?></p>
                <?php elseif (empty($result)): ?>
                    <p class="bg-success">Good job!! No diffs with core files =)</p>
                <?php else: ?>
                    <pre><?php echo nl2br($result); ?></pre>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file">Upload your project files</label>
                    <input type="file" name="file" id="file"/>
                    <span id="helpBlock" class="help-block">Allowed extensions: .tar.gz, .zip</span>
                </div>
                <button type="submit" name="submit" class="btn btn-primary">Check!</button>
            </form>

        </div>
    </div>
</div>
</body>
</html>
