(defn repeat-str (n str lst)
  (cond [(= n 0) (apply join "" lst)]
        [else (repeat-str (dec n) str (cons str lst))]))

(defn make-stars (n)
  (repeat-str n "*" '()))

(defn make-padding (n)
  (repeat-str n " " '()))

(defn make-row-str (padding stars)
  (str (make-padding padding) (make-stars stars) "\n"))

(defn make-rows (n height lst)
  (cond [(= n 0) lst]
        [else (let [padding (- height n)
                    stars (+ 1 (* 2 (- n 1)))]
                   (make-rows (dec n) height (cons (make-row-str padding stars) lst)))]))

(defn print-rows (rows)
  (cond [(empty? rows) null]
        [else (do
               (print (car rows))
               (print-rows (cdr rows)))]))

(defn print-pyramid (height)
  (let [rows (make-rows height height '())]
       (print-rows rows)))

(print-pyramid 5)
