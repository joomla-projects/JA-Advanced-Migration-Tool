<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

?>

<form action="<?php echo Route::_('index.php?option=com_cmsmigrator&task=import.import'); ?>" method="post"
      name="adminForm" id="migration-form" class="form-validate" enctype="multipart/form-data">

    <div class="form-horizontal">
        <?php echo $this->form->renderField('source_cms'); ?>
        <?php echo $this->form->renderField('import_file'); ?>
        <?php echo $this->form->renderField('source_url'); ?>
    </div>

    <input type="hidden" name="task" value="import.import"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
