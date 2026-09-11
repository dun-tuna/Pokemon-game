# Ban tổ chức — Cybermon v12

Tài liệu này chứa lời giải. Không đưa cho người chơi.

## Thiết kế

Màn 1 dạy đọc ý đồ và quản lý HP. Hạ 6 quái, HP 80–140 và damage 42–72. Màn 2 thử sức bền với hai hộ vệ 240 HP, đổi hệ sau mỗi lượt và tăng 4 damage. Năm đấu sĩ phụ (140 HP) là lựa chọn đánh đổi HP lấy +3 damage / 1 bình. Cả ba starter có đường thắng; không cần may mắn về hệ.

Đúng thế: gây 85% damage trước hệ, nhận 10% damage địch ở Màn 1 / 14% ở Màn 2. Thủ khắc chế Đánh nhận 0. Hòa: 35% / 20%. Sai: 12% / 42%. Hạ quái kết thúc trận trước khi bị đánh trả. Bình +45 HP, dùng khi địch Thủ không nhận damage; lúc khác nhận 30% damage địch có tính hệ.

## Puzzle Màn 3: untrusted effect / phản đòn

Boss ép cả HP và damage về 1 khi chạm trán và trước mọi hành động. Sửa damage không thể thắng. Cơ chế mới vẫn dùng PHP deserialize với allowlist chỉ có `Trainer`, không cần đoán tên class hay xem source để giải.

Giải mã Base64 toàn bộ file `.sav` để nhận PHP serialized object. Save có trường `technique` và một danh mục `technique_catalog` gồm `strike / guard / break / reflect`. Người chơi tự đọc dữ liệu save, suy luận hiệu ứng phản đòn và chọn hành động tương ứng. UI không có rescue hint hay đoạn code lời giải. Có thể gợi ý bằng lời theo mức: “Còn gì ngoài sức mạnh?”, “Xem hiệu ứng trong dữ liệu nhân vật”, rồi mới nói về phản đòn nếu cần.

Lời giải:

1. Qua hai màn đầu, lưu game ở Màn 3 (có thể lưu cả khi đang gặp boss).
2. Giải mã Base64 của file, rồi đổi đúng trường kỹ năng, không sửa checkpoint hoặc seal:

```text
s:9:"technique";s:6:"strike";
```

thành:

```text
s:9:"technique";s:7:"reflect";
```

3. Mã hóa lại toàn bộ object thành Base64, lưu vào `.sav`. Load lại, gặp boss và chọn **THỦ**. HP / damage vẫn là 1 nhưng đòn hủy diệt bị phản ngược. Server mới chuyển sang màn thắng và trả flag.

Script kiểm chứng:

```bash
python3 - <<'PY'
from pathlib import Path
import base64
source = base64.b64decode(Path('arena.sav').read_bytes(), validate=True)
old = b's:9:"technique";s:6:"strike";'
new = b's:9:"technique";s:7:"reflect";'
assert old in source, 'Không tìm thấy kỹ năng gốc'
Path('arena-reflect.sav').write_bytes(base64.b64encode(source.replace(old, new, 1)))
PY
```

Chỉ đổi catalog không có tác dụng. `reflect` đi cùng Đánh / Phá thủ / Bình vẫn thua. Phải chọn Thủ.

## Ranh giới của bài CTF

Toàn bộ file là `Base64(serialize(Trainer))`; Base64 là encoding, không phải mã hóa bảo mật. Loader chỉ nhận envelope hợp lệ (cho phép xuống dòng), không nhận object thô.

`checkpoint` bên trong vẫn là JSON base64 có HMAC-SHA256 (`seal`), lưu toàn bộ state cùng starter/name/HP/damage. Loader phục hồi các dữ liệu đó từ checkpoint được ký, bỏ qua các trường số bên ngoài. `technique` cố ý không thuộc phần ký và được tin cậy khi load — đây là điểm yếu cần khai thác. Checkpoint ký không phải mã hóa; đọc được nhưng không chỉnh được nếu không có key. Không có gadget chạy lệnh, ghi file tùy ý hay deserialize class ngoài Trainer.

Replay save hợp lệ được cho phép để chơi lại/checkpoint. Old save và checkpoint sửa stage/pending/wins đều bị từ chối. Save sau victory vẫn có thể phục hồi victory vì người chơi đã thực sự thắng trước đó. SAVE_SECRET phải giữ bí mật và ổn định trên deployment; xem README.

## Kiểm chứng

Đã chạy 12 lượt end-to-end qua CGI PHP 8.3 WebAssembly (4 lượt cho mỗi starter), gồm chiến thuật thắng, boss suppression, damage tampering, đúng kỹ năng phản đòn, checkpoint giả và load giữa trận. Bản test có hơn 700 request. Cần chạy lại `./smoke-test.sh` trên Apache/Docker thật vì runtime thử không thay thế kiểm tra cấu hình triển khai.
