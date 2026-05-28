using CW3.Task3;
using CW3.Task6;
using CW3.Task8;
using CW3.Task13;
using CW3.Task15;
using CW3.Task21;
using CW3.Task23;
using CW3.Task27;
using CW3.Task29;
using CW3.Task33;

namespace CW3;

public class Program
{
    public static void Main(string[] args)
    {
        // Задачи, для которых требуется таблица тестирования
        Tests3.Test();
        Tests13.Test();
        Tests15.Test();
        Tests23.Test();
        Tests27.Test();

        // Задачи без тестов — только демонстрация решения на примере из условия
        CW3.Task6.Task6.ExecuteTask();
        CW3.Task8.Task8.ExecuteTask();
        CW3.Task21.Task21.ExecuteTask();
        CW3.Task29.Task29.ExecuteTask();
        CW3.Task33.Task33.ExecuteTask();
    }
}
