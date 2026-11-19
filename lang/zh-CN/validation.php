<?php

return [
    'required' => ':attribute不能为空。',
    'required_if' => '当:other为:value时，:attribute不能为空。',
    'string' => ':attribute必须是文本。',
    'email' => ':attribute必须是有效的电子邮箱地址。',
    'unique' => ':attribute已被使用。',
    'confirmed' => ':attribute两次输入不一致。',
    'current_password' => '密码不正确。',
    'array' => ':attribute必须是列表。',
    'file' => ':attribute必须是文件。',
    'uploaded' => ':attribute上传失败。',
    'in' => '所选:attribute无效。',
    'uuid' => ':attribute必须是有效的标识符。',
    'accepted' => '请确认:attribute。',
    'distinct' => ':attribute中存在重复值。',
    'integer' => ':attribute必须是整数。',
    'min' => ['string' => ':attribute至少需要:min个字符。', 'array' => ':attribute至少需要:min项。', 'numeric' => ':attribute不能小于:min。', 'file' => ':attribute不能小于:min KB。'],
    'max' => ['string' => ':attribute不能超过:max个字符。', 'array' => ':attribute不能超过:max项。', 'numeric' => ':attribute不能超过:max。', 'file' => ':attribute不能超过:max KB。'],
    'password' => ['letters' => ':attribute必须包含字母。', 'mixed' => ':attribute必须包含大小写字母。', 'numbers' => ':attribute必须包含数字。', 'symbols' => ':attribute必须包含符号。', 'uncompromised' => '此:attribute已在数据泄露中出现，请选择其他密码。'],
    'attributes' => ['q' => '搜索词', 'name' => '名称', 'email' => '电子邮箱', 'password' => '密码', 'path' => '路径', 'sources' => '所选项目', 'files' => '文件', 'files.*' => '文件', 'content' => '文件内容', 'action' => '操作', 'confirm' => '永久删除', 'id' => '标识符'],
];
