<?php
class LeaveAttachment {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function create(array $data): bool {
        $stmt = $this->conn->prepare("
            INSERT INTO leave_attachments (
                leave_request_id,
                original_name,
                stored_name,
                file_path,
                mime_type,
                file_size,
                document_type,
                uploaded_by_user_id
            ) VALUES (
                :leave_request_id,
                :original_name,
                :stored_name,
                :file_path,
                :mime_type,
                :file_size,
                :document_type,
                :uploaded_by_user_id
            )
        ");

        return $stmt->execute([
            ':leave_request_id' => (int)($data['leave_request_id'] ?? 0),
            ':original_name' => (string)($data['original_name'] ?? ''),
            ':stored_name' => (string)($data['stored_name'] ?? ''),
            ':file_path' => (string)($data['file_path'] ?? ''),
            ':mime_type' => (string)($data['mime_type'] ?? ''),
            ':file_size' => (int)($data['file_size'] ?? 0),
            ':document_type' => (string)($data['document_type'] ?? 'general'),
            ':uploaded_by_user_id' => !empty($data['uploaded_by_user_id']) ? (int)$data['uploaded_by_user_id'] : null,
        ]);
    }

    public function getGroupedByLeaveIds(array $leaveIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $leaveIds), fn($id) => $id > 0)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare("
            SELECT *
            FROM leave_attachments
            WHERE leave_request_id IN ($placeholders)
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute($ids);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['leave_request_id']][] = $row;
        }
        return $out;
    }

    public function getByLeaveId(int $leaveId): array {
        if ($leaveId <= 0) {
            return [];
        }
        $stmt = $this->conn->prepare("
            SELECT *
            FROM leave_attachments
            WHERE leave_request_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$leaveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
