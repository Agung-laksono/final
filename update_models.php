<?php
$dir = "Modules/Marketing/app/Models";

// Project Model
$projectModel = file_get_contents("$dir/MarketingProject.php");
$relations = <<<PHP
    protected \$guarded = [];
    
    public function labels() { return \$this->belongsToMany(MarketingLabel::class, 'marketing_project_label'); }
    public function subtasks() { return \$this->hasMany(MarketingSubtask::class); }
    public function comments() { return \$this->hasMany(MarketingComment::class); }
    public function attachments() { return \$this->hasMany(MarketingAttachment::class); }
    public function activities() { return \$this->hasMany(MarketingActivity::class); }
    public function timeLogs() { return \$this->hasMany(MarketingTimeLog::class); }
    public function assignees() { return \$this->belongsToMany(\App\Models\User::class, 'marketing_project_user'); }
    public function linkedFrom() { return \$this->hasMany(MarketingProjectLink::class, 'to_project_id'); }
    public function linkedTo() { return \$this->hasMany(MarketingProjectLink::class, 'from_project_id'); }
PHP;
$projectModel = preg_replace('/protected \$fillable = \[\];/', $relations, $projectModel);
file_put_contents("$dir/MarketingProject.php", $projectModel);

// Label Model
$labelModel = file_get_contents("$dir/MarketingLabel.php");
$relations = <<<PHP
    protected \$guarded = [];
    public function projects() { return \$this->belongsToMany(MarketingProject::class, 'marketing_project_label'); }
PHP;
$labelModel = preg_replace('/protected \$fillable = \[\];/', $relations, $labelModel);
file_put_contents("$dir/MarketingLabel.php", $labelModel);

// Subtask Model
$subModel = file_get_contents("$dir/MarketingSubtask.php");
$relations = <<<PHP
    protected \$guarded = [];
    public function project() { return \$this->belongsTo(MarketingProject::class); }
PHP;
$subModel = preg_replace('/protected \$fillable = \[\];/', $relations, $subModel);
file_put_contents("$dir/MarketingSubtask.php", $subModel);

// Comment Model
$comModel = file_get_contents("$dir/MarketingComment.php");
$relations = <<<PHP
    protected \$guarded = [];
    public function project() { return \$this->belongsTo(MarketingProject::class); }
    public function user() { return \$this->belongsTo(\App\Models\User::class); }
PHP;
$comModel = preg_replace('/protected \$fillable = \[\];/', $relations, $comModel);
file_put_contents("$dir/MarketingComment.php", $comModel);

// Attachment Model
$attModel = file_get_contents("$dir/MarketingAttachment.php");
$relations = <<<PHP
    protected \$guarded = [];
    public function project() { return \$this->belongsTo(MarketingProject::class); }
    public function user() { return \$this->belongsTo(\App\Models\User::class); }
PHP;
$attModel = preg_replace('/protected \$fillable = \[\];/', $relations, $attModel);
file_put_contents("$dir/MarketingAttachment.php", $attModel);

// Activity Model
$actModel = file_get_contents("$dir/MarketingActivity.php");
$relations = <<<PHP
    protected \$guarded = [];
    public function project() { return \$this->belongsTo(MarketingProject::class); }
    public function user() { return \$this->belongsTo(\App\Models\User::class); }
PHP;
$actModel = preg_replace('/protected \$fillable = \[\];/', $relations, $actModel);
file_put_contents("$dir/MarketingActivity.php", $actModel);

// Time Log Model
$timeModel = file_get_contents("$dir/MarketingTimeLog.php");
$relations = <<<PHP
    protected \$guarded = [];
    public function project() { return \$this->belongsTo(MarketingProject::class); }
    public function user() { return \$this->belongsTo(\App\Models\User::class); }
PHP;
$timeModel = preg_replace('/protected \$fillable = \[\];/', $relations, $timeModel);
file_put_contents("$dir/MarketingTimeLog.php", $timeModel);

// Project Link Model
$linkModel = file_get_contents("$dir/MarketingProjectLink.php");
$relations = <<<PHP
    protected \$guarded = [];
    public function fromProject() { return \$this->belongsTo(MarketingProject::class, 'from_project_id'); }
    public function toProject() { return \$this->belongsTo(MarketingProject::class, 'to_project_id'); }
PHP;
$linkModel = preg_replace('/protected \$fillable = \[\];/', $relations, $linkModel);
file_put_contents("$dir/MarketingProjectLink.php", $linkModel);

echo "Models updated.";
