# v10 — Tactical / Endurance / Paradox

- Giữ title, tên các khu vực, nội dung flag, nhạc, sprite và giao diện pixel của bản đã duyệt.
- Màn 1: 6 trận nhiều lượt, ý đồ báo trước, tam giác thế đánh, HP giữ qua trận và 3 bình hồi phục.
- Màn 2: 2 hộ vệ đổi hệ/tăng damage qua lượt; 5 đối thủ phụ thưởng tài nguyên; phải hạ cả hai hộ vệ.
- Màn 3: triệt tiêu HP/damage về 1 ở server, puzzle kỹ năng phản đòn thay cho tăng chỉ số.
- Save lưu cả trận đang đánh, quái đã hạ, HP/bình và trạng thái chờ chuyển màn. Checkpoint có HMAC; trường hiệu ứng cố ý không được bảo vệ để tạo puzzle.
- Enter/Space xử lý một lần; chọn 5 nút bằng mũi tên; banner thua và nút tiếp tục có focus.
- Hướng dẫn mới mô tả luật combat, không hiện lời giải boss. Lời giải và cách sửa serialized length nằm trong ORGANIZER-SOLUTION.md.
- Session phiên bản trước tự bắt đầu lại; file save cũ bị từ chối. Cấu hình SAVE_SECRET theo README để giữ checkpoint qua redeploy.

## Kiểm tra đã thực hiện

- 12 lượt trên cả 3 starter với PHP 8.3 CGI WebAssembly, 739 request; kiểm tra chiến thuật thắng, save/load giữa battle, boss suppression, đường giải mới và checkpoint giả.
- DOM chạy JavaScript thật với API PHP thật: 5 nút, Enter/Space một request, HP render, clear focus, defeat/retry.
- Kiểm tra cú pháp JS/Bash và tính toàn vẹn ZIP.
- Chưa kiểm tra hình ảnh trực tiếp bằng trình duyệt hoặc container Apache/Docker; tải browser trong môi trường kiểm tra bị timeout. Chạy ./smoke-test.sh trên deployment và chơi thử trước hội trại.
