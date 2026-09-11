# Organizer Solution — Cybermon Glitch Arena

Tài liệu này dành cho ban tổ chức. Không gửi file source/ZIP hoặc file này cho người chơi nếu muốn giữ bí mật lời giải.

## Intended solve path

1. Chọn starter và dùng các phím ↑ ↓ ← → để đi chạm 4 hotspot ở Màn 1. Vị trí các quái thường được xáo trộn sau mỗi lượt chơi.
2. Trong mỗi encounter, damage của trainer lớn hơn damage Wild Pikachu nên chỉ cần **Đánh một đòn**.
3. Bấm **Chúc mừng! → Sang màn 2**.
4. Ở Màn 2 có 7 hotspot được xáo vị trí, gồm type có lợi cho starter, cùng type và type counter starter. Không có nhãn tiết lộ đáp án: người chơi tự đọc type trên hotspot và bảng tương khắc. Đối thủ có type có lợi luôn chịu debuff nhưng vẫn thua một đòn và lập tức mở Màn 3. Đối thủ cùng type chỉ qua được battle nếu damage của trainer lớn hơn; thắng cùng hệ chưa mở màn mới. Đối thủ counter gây debuff và thua ngay. Nút **Chạy** luôn hợp lệ để đổi đối thủ.
5. Chạm hotspot có lợi, thắng một trận và bấm **Chúc mừng! → Sang màn 3**.
6. Sang Màn 3, đi tới NullByte Ω và nhấn **Đánh một đòn**. Boss có `99.999` damage nên đòn bình thường không đủ; nếu thất bại, server giữ lý do trong banner, rồi nút **Thử lại** mới đưa người chơi về Màn 1.
7. Nút **Lưu game** luôn có trên thanh trên. File save là PHP serialized object, trong đó có trường `damage`.
8. Sửa giá trị `damage` thành một số lớn hơn `99.999`, ví dụ `100000`, rồi nạp lại file.
9. Quay lại NullByte và nhấn **Đánh một đòn** lần nữa. Damage mới vượt boss và flag xuất hiện.

## Tạo payload kiểm thử nhanh

Sau khi tải `arena.sav` vào thư mục hiện tại:

```bash
python3 - <<'PY'
from pathlib import Path
import re

source = Path("arena.sav")
target = Path("arena-hacked.sav")
data = source.read_bytes()

pattern = rb'(s:6:"damage";i:)\d+(;)'
data, count = re.subn(pattern, rb'\g<1>100000\2', data, count=1)
if count != 1:
    raise SystemExit("Không tìm thấy trường damage trong serialized Trainer")

target.write_bytes(data)
print(f"Created {target}")
PY
```

Upload `arena-hacked.sav`, quay lại NullByte và nhấn **Đánh một đòn**.

## Vì sao đây là PHP object deserialization?

Server deserialize dữ liệu do người chơi kiểm soát rồi dùng các thuộc tính trong object để tính damage. Màn 3 cố ý cho phép sửa có giới hạn trường `damage`, nhưng không cho đổi class và không có gadget chain dẫn tới RCE.

Giới hạn triển khai:

- `unserialize()` chỉ cho phép class `Trainer`.
- Màn 1/2 kẹp damage theo số trận thắng hợp lệ.
- Màn 3 cho phép damage tối đa `1.000.000`, đủ để vượt boss nhưng không tạo giá trị vô hạn.

## Gợi ý theo từng mức

- Hint 1: “Đòn đánh hiện tại chưa đủ mạnh. Hãy để ý damage của Boss.”
- Hint 2: “Nút Lưu game luôn có trên thanh trên; file save chứa dữ liệu Trainer.”
- Hint 3: “Tìm trường `damage` trong file save và chỉnh lớn hơn `99.999`.”

## Checklist trước giờ mở cửa

```bash
cp .env.example .env
docker compose up --build -d
docker compose ps
docker compose logs --tail=50 arena
./smoke-test.sh
```

Kiểm tra trên một cửa sổ ẩn danh:

- Màn 1 di chuyển bằng arrow keys và có 4 hotspot.
- Mỗi trận so damage và kết thúc bằng một đòn.
- Màn 1 có dòng chúc mừng trước khi sang Màn 2.
- Màn 2 có 7 đối thủ, không có nhãn “đúng hệ/counter”; kiểm tra đủ nhóm có lợi, cùng hệ và counter. Trận có lợi có debuff nhưng thắng và mở Màn 3; trận cùng hệ chỉ thắng khi damage lớn hơn và không mở màn; trận counter hiển thị banner thất bại. Nút **Chạy** không làm mất lượt.
- Vị trí Màn 1/2 thay đổi giữa các lượt, còn vị trí boss không đổi.
- Save tải/nạp được từ Màn 1, Màn 2 và Màn 3.
- Save gốc không thể hạ NullByte.
- Thua ở Màn 1, Màn 2 hoặc Màn 3 đều hiển thị banner lý do; nút **Thử lại** có thể kích hoạt bằng Enter/Space và đưa về Màn 1.
- Save sửa trường damage thành `100000` load thành công và nhận đúng `FINAL_FLAG`.
- Màn 3 hiển thị flag sau dòng chúc mừng hoàn tất.
- Nút qua màn nhận focus và hoạt động với Enter/Space.
- Nút Chơi lại tạo session mới.
